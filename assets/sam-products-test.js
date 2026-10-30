(() => {
    const form = document.querySelector('[data-sam-search-form]');
    const input = document.querySelector('[data-sam-search-input]');
    const suggestions = document.querySelector('[data-sam-suggestions]');
    if (!form || !input || !suggestions) return;

    let debounceTimer = null;
    let activeRequest = null;
    let activeIndex = -1;

    const closeSuggestions = () => {
        suggestions.replaceChildren();
        suggestions.classList.add('d-none');
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    };

    const chooseSuggestion = (result) => {
        input.value = result.cnk || '';
        closeSuggestions();
        form.requestSubmit();
    };

    const renderSuggestions = (results) => {
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
            title.textContent = [result.cnk, '–', result.prescription_name_nl || result.prescription_name_fr || result.official_name, packageText, statusCodes, result.atc_code]
                .filter(Boolean)
                .join(' ');
            button.append(title);
            button.addEventListener('click', () => chooseSuggestion(result));
            suggestions.append(button);
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
        url.searchParams.set('limit', '10');
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
            renderSuggestions(Array.isArray(data.results) ? data.results : []);
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
