(function (global) {
	'use strict';

	var callbacks = [];
	var isLoading = false;
	var hasScriptTag = false;

	function flushCallbacks(error) {
		var pending = callbacks.slice();
		callbacks.length = 0;
		pending.forEach(function (entry) {
			try {
				if (error) {
					if (typeof entry.onError === 'function') {
						entry.onError(error);
					}
				} else if (typeof entry.onSuccess === 'function') {
					entry.onSuccess();
				}
			} catch (err) {
				console.error('BroManMaps callback error:', err);
			}
		});
	}

	function loadGoogleMaps(apiKey, onSuccess, onError) {
		if (global.google && global.google.maps) {
			if (typeof onSuccess === 'function') {
				onSuccess();
			}
			return;
		}

		if (!apiKey) {
			var error = new Error('GOOGLE_MAPS_API_KEY is missing. Set it in config.php.');
			console.error(error.message);
			if (typeof onError === 'function') {
				onError(error);
			}
			return;
		}

		callbacks.push({
			onSuccess: onSuccess,
			onError: onError
		});

		if (isLoading) {
			return;
		}

		isLoading = true;

		if (!hasScriptTag) {
			var script = document.createElement('script');
			script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&callback=__broManOnGoogleMapsLoaded';
			script.async = true;
			script.defer = true;
			script.onerror = function () {
				var loadError = new Error('Failed to load Google Maps JavaScript API.');
				console.error(loadError.message);
				flushCallbacks(loadError);
			};
			document.head.appendChild(script);
			hasScriptTag = true;
		}

		global.__broManOnGoogleMapsLoaded = function () {
			flushCallbacks();
		};
	}

	function showError(element, message) {
		if (!element) {
			return;
		}
		element.innerHTML = '<div style="padding:16px; text-align:center; color:#d9534f;">' + (message || 'تعذر تحميل خريطة جوجل.') + '</div>';
		element.style.display = 'flex';
		element.style.alignItems = 'center';
		element.style.justifyContent = 'center';
	}

	global.BroManMaps = {
		loadGoogleMaps: loadGoogleMaps,
		showError: showError
	};
})(window);

