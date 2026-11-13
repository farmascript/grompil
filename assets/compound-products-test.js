(() => {
    const form = document.querySelector('[data-compound-search-form]');
    const input = document.querySelector('[data-compound-search-input]');
    const suggestions = document.querySelector('[data-compound-suggestions]');
    const selectedMaterial = form?.querySelector('[data-selected-material]');
    if (!form || !input || !suggestions || !selectedMaterial) return;

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
        selectedMaterial.value = String(result.id);
        closeSuggestions();
        form.requestSubmit();
    };

    const renderSuggestions = (results, totalCount) => {
        suggestions.replaceChildren();
        if (!results.length) {
            closeSuggestions();
            return;
        }

        results.forEach((result) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false');

            const language = (form.dataset.language || 'nl').toLowerCase();
            const name = (language === 'fr' ? result.name_fr : result.name_nl)
                || result.name_nl
                || result.name_fr
                || '—';
            const title = document.createElement('span');
            title.className = 'd-block fw-semibold text-truncate';
            title.textContent = name;
            button.append(title);

            const identifiers = [];
            const sourceLabel = language === 'fr' ? 'Source' : language === 'de' ? 'Quelle' : language === 'en' ? 'Source' : 'Bron';
            const cnkLabel = language === 'fr' ? 'CNK' : 'CNK';
            const reimbursementLabel = language === 'fr' ? 'INAMI' : language === 'de' ? 'INAMI' : language === 'en' ? 'INAMI' : 'RIZIV';
            if (result.cas_number) identifiers.push(`CAS ${result.cas_number}`);
            if (result.ec_number) identifiers.push(`EC ${result.ec_number}`);
            if (Array.isArray(result.source_ids)) identifiers.push(...result.source_ids.map((id) => `${sourceLabel} ${id}`));
            for (const code of result.reimbursement || []) {
                const chapter = [code.chapter_code, code.list_number].filter(Boolean).join(' / ');
                if (code.cnk) identifiers.push(`${cnkLabel} ${code.cnk}`);
                const description = language === 'fr'
                    ? (code.description_fr || code.description_nl)
                    : (code.description_nl || code.description_fr);
                if (chapter) {
                    identifiers.push(`${reimbursementLabel} ${chapter}${description ? ` — ${description}` : ''}`);
                } else if (description) {
                    identifiers.push(description);
                }
            }
            const secondaryText = [...new Set(identifiers)].join(' · ');
            if (secondaryText) {
                const secondary = document.createElement('span');
                secondary.className = 'd-block small text-secondary text-truncate';
                secondary.textContent = secondaryText;
                button.append(secondary);
            }

            button.addEventListener('click', () => chooseSuggestion(result));
            suggestions.append(button);
        });

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
        activeIndex = -1;
    };

    const search = async () => {
        const query = input.value.trim();
        if (Array.from(query).length < 3) {
            if (activeRequest) activeRequest.abort();
            closeSuggestions();
            return;
        }

        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        const url = new URL(form.dataset.searchUrl, window.location.href);
        url.searchParams.set('q', query);
        url.searchParams.set('limit', form.dataset.searchLimit || '20');

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
                Number.isInteger(data.total_count) ? data.total_count : 0
            );
        } catch (error) {
            if (error.name !== 'AbortError') {
                suggestions.replaceChildren();
                const message = document.createElement('div');
                message.className = 'list-group-item text-danger';
                message.textContent = form.dataset.searchError || 'Search unavailable';
                suggestions.append(message);
                suggestions.classList.remove('d-none');
                input.setAttribute('aria-expanded', 'true');
            }
        }
    };

    input.addEventListener('input', () => {
        selectedMaterial.value = '';
        if (activeRequest) activeRequest.abort();
        closeSuggestions();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(search, 250);
    });

    input.addEventListener('keydown', (event) => {
        const options = [...suggestions.querySelectorAll('[role="option"]')];
        if (suggestions.classList.contains('d-none') || options.length === 0) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length;
            options.forEach((option, index) => {
                option.classList.toggle('active', index === activeIndex);
                option.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
            });
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            options[activeIndex].click();
        } else if (event.key === 'Escape') {
            closeSuggestions();
        }
    });

    form.addEventListener('submit', (event) => {
        if (!selectedMaterial.value) closeSuggestions();
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) closeSuggestions();
    });
})();
