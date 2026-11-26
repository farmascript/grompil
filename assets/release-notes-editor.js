(function () {
  'use strict';

  if (typeof window.Quill !== 'function') return;

  document.querySelectorAll('.release-note-editor-field').forEach(function (field) {
    var textarea = field.querySelector('textarea');
    if (!textarea) return;

    var shouldFocus = textarea.hasAttribute('autofocus');
    var editorHost = document.createElement('div');
    editorHost.className = 'release-note-editor';
    var label = field.querySelector('label');
    editorHost.setAttribute('aria-label', label ? label.textContent.trim() : textarea.id);
    textarea.insertAdjacentElement('afterend', editorHost);

    var quill = new window.Quill(editorHost, {
      theme: 'snow',
      formats: ['bold', 'italic', 'underline', 'list', 'link'],
      modules: {
        toolbar: [
          ['bold', 'italic', 'underline'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link']
        ]
      }
    });

    if (textarea.value.trim() !== '') {
      quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
    }

    textarea.classList.add('release-note-editor-source');
    editorHost.insertAdjacentElement('afterend', textarea);
    textarea.removeAttribute('required');
    textarea.removeAttribute('autofocus');

    var sourceButton = document.createElement('button');
    sourceButton.type = 'button';
    sourceButton.className = 'release-note-source-toggle';
    sourceButton.setAttribute('aria-label', field.dataset.htmlModeLabel || 'HTML');
    sourceButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m5.5 4.5-3.5 3.5 3.5 3.5M10.5 4.5 14 8l-3.5 3.5M9 3l-2 10"/></svg>';
    sourceButton.setAttribute('aria-pressed', 'false');
    sourceButton.title = field.dataset.htmlModeLabel || 'HTML';
    var toolbar = field.querySelector('.ql-toolbar');
    if (toolbar) {
      var formatGroup = document.createElement('span');
      formatGroup.className = 'ql-formats';
      formatGroup.appendChild(sourceButton);
      toolbar.appendChild(formatGroup);
    }

    sourceButton.addEventListener('click', function () {
      var showingSource = field.classList.toggle('release-note-html-mode');
      if (showingSource) {
        textarea.value = quill.getSemanticHTML();
        textarea.focus();
      } else {
        quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
        quill.focus();
      }
      var modeLabel = showingSource
        ? (field.dataset.visualModeLabel || 'Visual editor')
        : (field.dataset.htmlModeLabel || 'HTML');
      sourceButton.title = modeLabel;
      sourceButton.setAttribute('aria-label', modeLabel);
      sourceButton.setAttribute('aria-pressed', showingSource ? 'true' : 'false');
    });

    if (shouldFocus) quill.focus();

    var form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (!field.classList.contains('release-note-html-mode')) {
          textarea.value = quill.getSemanticHTML();
        }
      });
    }
  });
})();
