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
    textarea.removeAttribute('required');
    textarea.removeAttribute('autofocus');
    if (shouldFocus) quill.focus();

    var form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        textarea.value = quill.getSemanticHTML();
      });
    }
  });
})();
