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
    sourceButton.textContent = field.dataset.htmlModeLabel || 'HTML';
    sourceButton.setAttribute('aria-pressed', 'false');
    sourceButton.title = field.dataset.htmlModeLabel || 'HTML';
    var toolbar = editorHost.querySelector('.ql-toolbar');
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
      sourceButton.textContent = showingSource
        ? (field.dataset.visualModeLabel || 'Visual editor')
        : (field.dataset.htmlModeLabel || 'HTML');
      sourceButton.title = sourceButton.textContent;
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
