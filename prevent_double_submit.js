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
	});
});


