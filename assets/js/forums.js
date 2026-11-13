(function () {
	'use strict';

	function initialize() {
		document.querySelectorAll('.psforum-post-form').forEach(function (form) {
			const button = form.querySelector('.psforum-preview-button');
			const preview = form.querySelector('.psforum-preview');
			button.hidden = false;
			form.querySelector('textarea').addEventListener('input', function () {
				preview.hidden = true;
				button.setAttribute('aria-expanded', 'false');
			});
			button.addEventListener('click', async function () {
				button.disabled = true;
				preview.hidden = false;
				preview.setAttribute('aria-busy', 'true');
				button.setAttribute('aria-expanded', 'true');
				try {
					const data = new FormData(form);
					data.set('_psforum_nonce', form.dataset.previewNonce);
					const response = await fetch(form.dataset.previewUrl, { method: 'POST', body: data, credentials: 'same-origin' });
					if (!response.ok) {
						throw new Error('HTTP ' + response.status);
					}
					const result = await response.json();
					if (!result.success || typeof result.data.html !== 'string') {
						throw new Error('Invalid preview response');
					}
					preview.innerHTML = result.data.html;
				} catch (error) {
					preview.textContent = form.dataset.previewError;
					console.error('Quick Forum preview:', error);
				} finally {
					preview.removeAttribute('aria-busy');
					button.disabled = false;
				}
			});
		});
		document.querySelectorAll('.psforum form').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				if (form.dataset.submitting === 'true') {
					event.preventDefault();
					return;
				}
				form.dataset.submitting = 'true';
				form.setAttribute('aria-busy', 'true');
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
	window.addEventListener('pageshow', function () {
		document.querySelectorAll('.psforum form').forEach(function (form) {
			delete form.dataset.submitting;
			form.removeAttribute('aria-busy');
		});
	});
}());
