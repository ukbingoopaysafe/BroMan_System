document.addEventListener('DOMContentLoaded', function() {
	var forms = document.querySelectorAll('form[data-prevent-multi-submit="1"]');
	forms.forEach(function(form) {
		form.addEventListener('submit', function(e) {
			var btn = form.querySelector('button[type="submit"], input[type="submit"]');
			if (btn && btn.dataset.submitting === '1') {
				e.preventDefault();
				return false;
			}
			if (btn) {
				btn.dataset.submitting = '1';
				btn.disabled = true;
				var originalText = btn.innerHTML;
				btn.dataset.originalText = originalText;
				btn.innerHTML = (document.documentElement.dir === 'rtl') ? 'جاري الحفظ…' : 'Saving…';
			}
		});

		// Prevent form submission when pressing Enter inside inputs (except textareas/contenteditable)
		form.addEventListener('keydown', function(e) {
			var key = e.key || e.keyCode;
			if (key === 'Enter' || key === 13) {
				var target = e.target;
				if (!target) return;
				var tag = (target.tagName || '').toLowerCase();
				// Allow Enter in textarea or contenteditable areas
				if (tag === 'textarea' || target.isContentEditable) {
					return;
				}
				// Prevent Enter from submitting the form — require explicit button click
				e.preventDefault();
				return false;
			}
		});
	});
});

// As a stronger guard, listen in the capture phase at document-level to stop Enter
// before other handlers can call form.submit(). This only affects forms with the
// `data-prevent-multi-submit="1"` attribute and ignores textareas/contenteditable.
document.addEventListener('keydown', function(e) {
    var key = e.key || e.keyCode;
    if (!(key === 'Enter' || key === 13)) return;
    var target = e.target;
    if (!target) return;
    var tag = (target.tagName || '').toLowerCase();
    if (tag === 'textarea' || target.isContentEditable) return;
    var form = target.closest && target.closest('form');
    if (!form) return;
    if (form.getAttribute && form.getAttribute('data-prevent-multi-submit') === '1') {
        e.preventDefault();
        // stop other handlers from receiving the event (best-effort)
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        return false;
    }
}, true);

// Also listen for keypress in capture phase (older handlers may use keypress)
document.addEventListener('keypress', function(e) {
	var key = e.key || e.keyCode;
	if (!(key === 'Enter' || key === 13)) return;
	var target = e.target;
	if (!target) return;
	var tag = (target.tagName || '').toLowerCase();
	if (tag === 'textarea' || target.isContentEditable) return;
	var form = target.closest && target.closest('form');
	if (!form) return;
	if (form.getAttribute && form.getAttribute('data-prevent-multi-submit') === '1') {
		e.preventDefault();
		if (e.stopImmediatePropagation) e.stopImmediatePropagation();
		return false;
	}
}, true);


