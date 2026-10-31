(() => {
    const form = document.querySelector('[data-sam-search-form]');
    const input = document.querySelector('[data-sam-search-input]');
    const suggestions = document.querySelector('[data-sam-suggestions]');
    const selectedSuggestion = form?.querySelector('[data-selected-suggestion]');
    if (!form || !input || !suggestions || !selectedSuggestion) return;

    let debounceTimer = null;
    let activeRequest = null;
    let activeIndex = -1;

    if (new URLSearchParams(window.location.search).get('selected_suggestion') === '1') {
        input.value = '';
        input.focus({ preventScroll: true });
    }

    const closeSuggestions = () => {
        suggestions.replaceChildren();
        suggestions.classList.add('d-none');
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    };

    const compactWordMatches = (query, value) => {
        const words = [...value.matchAll(/[\p{L}\p{N}]+/gu)];
        const normalizedQuery = query.toLocaleLowerCase();
        const findParts = (queryOffset, wordOffset) => {
            if (queryOffset === normalizedQuery.length) return [];
            for (let wordIndex = wordOffset; wordIndex < words.length; wordIndex += 1) {
                const word = words[wordIndex][0].toLocaleLowerCase();
                const maxLength = Math.min(word.length, normalizedQuery.length - queryOffset);
                for (let length = maxLength; length >= 2; length -= 1) {
                    const part = normalizedQuery.slice(queryOffset, queryOffset + length);
                    if (!word.startsWith(part)) continue;
                    const remainder = findParts(queryOffset + length, wordIndex + 1);
                    if (remainder) {
                        return [{ start: words[wordIndex].index, end: words[wordIndex].index + length }, ...remainder];
                    }
                }
            }
            return null;
        };
        return findParts(0, 0) || [];
    };

    const highlightText = (container, value, query) => {
        const isCompact = /^[\p{L}\p{N}]+$/u.test(query);
        let ranges = [];
        if (isCompact) {
            ranges = compactWordMatches(query, value);
        }
        if (ranges.length === 0) {
            const terms = query.toLocaleLowerCase().match(/[\p{L}\p{N}]+/gu) || [];
            for (const term of terms) {
                let offset = 0;
                const lowerValue = value.toLocaleLowerCase();
                while (offset < lowerValue.length) {
                    const start = lowerValue.indexOf(term, offset);
                    if (start < 0) break;
                    ranges.push({ start, end: start + term.length });
                    offset = start + term.length;
                }
            }
        }

        ranges.sort((left, right) => left.start - right.start);
        const mergedRanges = [];
        for (const range of ranges) {
            const previous = mergedRanges[mergedRanges.length - 1];
            if (previous && range.start <= previous.end) {
                previous.end = Math.max(previous.end, range.end);
            } else {
                mergedRanges.push({ ...range });
            }
        }

        let cursor = 0;
        for (const range of mergedRanges) {
            if (range.start > cursor) container.append(document.createTextNode(value.slice(cursor, range.start)));
            const mark = document.createElement('mark');
            mark.textContent = value.slice(range.start, range.end);
            container.append(mark);
            cursor = range.end;
        }
        if (cursor < value.length) container.append(document.createTextNode(value.slice(cursor)));
        if (mergedRanges.length === 0) container.textContent = value;
    };

    const chooseSuggestion = (result) => {
        input.value = result.cnk || '';
        selectedSuggestion.value = '1';
        closeSuggestions();
        form.requestSubmit();
    };

    const renderSuggestions = (results, query, totalCount) => {
        suggestions.replaceChildren();
        if (!results.length) {
            closeSuggestions();
            return;
        }

        for (const result of results) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.setAttribute('role', 'option');

            const title = document.createElement('span');
            title.className = 'd-block text-truncate';
            const packageText = result.pack_display_nl || result.pack_display_fr;
            const statusLetter = { AUTHORIZED: 'A', REVOKED: 'R', SUSPENDED: 'S' };
            const statusCodes = [result.amp_status, result.ampp_status]
                .filter(Boolean)
                .map((status) => statusLetter[status] || status)
                .filter((status, index, values) => values.indexOf(status) === index)
                .join('/');
            const description = result.prescription_name_nl || result.prescription_name_fr || result.official_name || '';
            const parts = [];
            const addPart = (text, highlight = false) => {
                if (!text) return;
                if (parts.length > 0) title.append(document.createTextNode(' '));
                if (highlight) highlightText(title, text, query);
                else title.append(document.createTextNode(text));
                parts.push(text);
            };
            addPart(result.cnk || '', /^[0-9 .-]+$/.test(query));
            if (result.cnk && (description || packageText || statusCodes || result.atc_class_path)) {
                addPart('–');
            }
            addPart(description, true);
            addPart(packageText, true);
            addPart(statusCodes);
            addPart(result.atc_class_path);
            button.append(title);
            button.addEventListener('click', () => chooseSuggestion(result));
            suggestions.append(button);
        }

        if (totalCount > results.length) {
            const more = document.createElement('div');
            more.className = 'list-group-item small text-secondary';
            more.textContent = (form.dataset.searchMore || '{shown} of {total} results shown')
                .replace('{shown}', String(results.length))
                .replace('{total}', String(totalCount));
            suggestions.append(more);
        }

        suggestions.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    };

    const search = async () => {
        const query = input.value.trim();
        if (query.length < 3) {
            if (activeRequest) activeRequest.abort();
            closeSuggestions();
            return;
        }

        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        const url = new URL(form.dataset.searchUrl, window.location.href);
        url.searchParams.set('q', query);
        const configuredLimit = Number.parseInt(form.dataset.searchLimit || '20', 10);
        url.searchParams.set('limit', String(Number.isInteger(configuredLimit) ? configuredLimit : 20));
        url.searchParams.set('status_filter', '1');
        form.querySelectorAll('[data-sam-status-filter]:checked').forEach((checkbox) => {
            url.searchParams.append('status[]', checkbox.value);
        });

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: activeRequest.signal,
            });
            if (!response.ok) throw new Error('Search unavailable');
            const data = await response.json();
            if (input.value.trim() !== query) return;
            renderSuggestions(
                Array.isArray(data.results) ? data.results : [],
                query,
                Number.isInteger(data.total_count) ? data.total_count : 0
            );
        } catch (error) {
            if (error.name !== 'AbortError') {
                suggestions.replaceChildren();
                const item = document.createElement('div');
                item.className = 'list-group-item text-danger';
                item.textContent = form.dataset.searchError || 'Search unavailable';
                suggestions.append(item);
                suggestions.classList.remove('d-none');
                input.setAttribute('aria-expanded', 'true');
            }
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(search, 250);
    });

    form.querySelectorAll('[data-sam-status-filter]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(search, 0);
        });
    });

    input.addEventListener('keydown', (event) => {
        const options = [...suggestions.querySelectorAll('[role="option"]')];
        if (suggestions.classList.contains('d-none') || options.length === 0) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length;
            options.forEach((option, index) => option.classList.toggle('active', index === activeIndex));
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            options[activeIndex].click();
        } else if (event.key === 'Escape') {
            closeSuggestions();
        }
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) closeSuggestions();
    });
})();
