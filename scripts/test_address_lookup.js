/**
 *
 * @date_cre: Fri 2026-10-23
 * @date_mut: Fri 2026-10-23
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Checks address selection, focus changes and delayed search responses.
 *
 */

const assert = require('assert').strict;
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function setup(source) {
    class Element {
        constructor() {
            this.children = [];
            this.listeners = {};
            this.value = '';
            this.classList = { add() {} };
        }
        addEventListener(type, handler) {
            (this.listeners[type] || (this.listeners[type] = [])).push(handler);
        }
        dispatch(type, properties = {}) {
            const event = { button: 0, relatedTarget: null, defaultPrevented: false,
                preventDefault() { this.defaultPrevented = true; }, ...properties };
            (this.listeners[type] || []).forEach((handler) => handler(event));
            return event;
        }
        append(child) { child.parentElement = this; this.children.push(child); }
        replaceChildren() { this.children = []; }
        contains(child) { return child === this || this.children.some((item) => item.contains(child)); }
        setAttribute() {}
    }
    const fields = {};
    for (const role of ['postal', 'city', 'street', 'house', 'box']) {
        fields[role] = new Element();
        fields[role].dataset = { addressRole: role };
        fields[role].parentElement = new Element();
    }
    const streetId = new Element();
    const referenceId = new Element();
    const group = {
        dataset: { addressCountry: 'BE' },
        querySelectorAll: () => Object.values(fields),
        querySelector: (selector) => selector === '[data-address-street-id]' ? streetId : referenceId,
    };
    const timers = new Map();
    const requests = [];
    let now = 0;
    let nextTimer = 0;
    vm.runInNewContext(source, {
        document: { baseURI: 'http://example.test/grompil/account.php',
            querySelectorAll: () => [group], createElement: () => new Element() },
        URL,
        setTimeout(handler, delay) { const id = ++nextTimer; timers.set(id, { handler, at: now + delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        fetch(url) {
            return new Promise((resolve) => requests.push({ url,
                resolve(items) { resolve({ ok: true, json: async () => ({ items }) }); } }));
        },
    });
    const flush = async () => { for (let i = 0; i < 10; i++) await Promise.resolve(); };
    const tick = async (milliseconds) => {
        now += milliseconds;
        for (const [id, timer] of [...timers]) {
            if (timer.at <= now) { timers.delete(id); timer.handler(); }
        }
        await flush();
    };
    const type = (role, value) => { fields[role].value = value; fields[role].dispatch('input'); };
    const list = (role) => fields[role].parentElement.children[0];
    const suggest = async (role, query, item) => {
        type(role, query);
        await tick(350);
        requests[requests.length - 1].resolve([item]);
        await flush();
        return list(role).children[0];
    };
    const slowClick = async (role, button) => {
        const event = button.dispatch('mousedown');
        if (!event.defaultPrevented) fields[role].dispatch('blur', { relatedTarget: button });
        await tick(250);
        // A removed button cannot receive the browser's eventual click.
        if (list(role).contains(button)) button.dispatch('click');
    };
    return { fields, streetId, referenceId, requests, tick, flush, type, list, suggest, slowClick };
}

async function run() {
    const source = fs.readFileSync(process.argv[2] || path.join(__dirname, '../assets/address-lookup.js'), 'utf8');
    const app = setup(source);
    const postal = { postal_code: '9000', city: 'Gent', label: '9000 Gent' };
    let button = await app.suggest('postal', '90', postal);
    await app.slowClick('postal', button);
    assert.equal(app.fields.postal.value, '9000');
    assert.equal(app.fields.city.value, 'Gent', 'Selecting a postal suggestion fills the city after a slow click');

    button = await app.suggest('street', 'Veld', { id: 'street-1', street: 'Veldstraat', label: 'Veldstraat' });
    assert.equal(app.requests[1].url.searchParams.get('postal_code'), '9000');
    assert.equal(app.requests[1].url.searchParams.get('city'), 'Gent');
    await app.slowClick('street', button);
    assert.equal(app.fields.street.value, 'Veldstraat');
    assert.equal(app.streetId.value, 'street-1');

    button = await app.suggest('city', 'Bru', { city: 'Brugge', label: 'Brugge' });
    app.fields.city.dispatch('blur', { relatedTarget: button });
    await app.tick(250);
    assert(app.list('city').contains(button), 'Tab or touch focus on a suggestion keeps it available');
    button.dispatch('click');
    assert.equal(app.fields.city.value, 'Brugge');
    assert.equal(app.streetId.value, '');

    button = await app.suggest('house', '1', {
        id: 'address-1', street: 'Veldstraat', house_number: '1', box_number: 'A',
        postal_code: '9000', city: 'Gent', label: 'Veldstraat 1 bus A, 9000 Gent',
    });
    await app.slowClick('house', button);
    assert.equal(app.fields.house.value, '1');
    assert.equal(app.fields.box.value, 'A');
    assert.equal(app.fields.city.value, 'Gent');
    assert.equal(app.referenceId.value, 'address-1');

    button = await app.suggest('postal', '90', postal);
    app.type('postal', '900');
    await app.tick(350);
    const pending = app.requests[app.requests.length - 1];
    await app.slowClick('postal', button);
    pending.resolve([{ ...postal, city: 'Late result' }]);
    await app.flush();
    assert.equal(app.list('postal').children.length, 0, 'A late response cannot reopen a selected list');
    assert.equal(app.fields.city.value, 'Gent');
    assert.equal(app.referenceId.value, '');

    app.type('postal', '90');
    await app.tick(350);
    const shortQuery = app.requests[app.requests.length - 1];
    app.type('postal', '9');
    shortQuery.resolve([postal]);
    await app.flush();
    assert.equal(app.list('postal').children.length, 0, 'Shortening a query invalidates its pending response');

    button = await app.suggest('postal', '90', postal);
    app.fields.postal.dispatch('blur', { relatedTarget: button });
    app.list('postal').dispatch('focusout', { relatedTarget: app.fields.street });
    assert.equal(app.list('postal').children.length, 0, 'Moving focus outside the suggestions dismisses them');

    app.type('postal', '90');
    await app.tick(350);
    const blurred = app.requests[app.requests.length - 1];
    app.fields.postal.dispatch('blur', { relatedTarget: app.fields.street });
    blurred.resolve([postal]);
    await app.flush();
    assert.equal(app.list('postal').children.length, 0, 'A response cannot reopen suggestions after leaving the field');
    console.log('Address lookup regression checks passed.');
}

run().catch((error) => { console.error(error); process.exitCode = 1; });
