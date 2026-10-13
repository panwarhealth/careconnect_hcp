/**
 * Video file box (admin-video-file.php): convert in a worker, upload to R2 with
 * a presigned URL, then save to the post over AJAX. Each step is saved as soon
 * as it finishes, so the editor's Update button is not needed for any of it.
 */
(function () {
	'use strict';

	var cfg = window.hcpVideoFile;
	var box = document.getElementById('hcp-vf');
	if (!cfg || !box) {
		return;
	}

	var preview = box.querySelector('.hcp-vf-preview video');
	var current = box.querySelector('.hcp-vf-current');
	var status = box.querySelector('.hcp-vf-status');
	var message = box.querySelector('.hcp-vf-message');
	var bar = box.querySelector('progress');
	var keepOpen = box.querySelector('.hcp-vf-keep-open');
	var frameButton = box.querySelector('.hcp-vf-frame');
	var captionsState = box.querySelector('.hcp-vf-captions-state');
	var captionsRemove = box.querySelector('.hcp-vf-captions-remove');
	var inputs = box.querySelectorAll('input[type=file]');
	var posterImg = box.querySelector('.hcp-vf-poster-img');
	var posterNone = box.querySelector('.hcp-vf-poster-none');
	var cardToggle = box.querySelector('.hcp-vf-endcard-toggle');
	var pageTitle = document.title;
	var busy = false;

	window.addEventListener('beforeunload', function (e) {
		if (busy) {
			e.preventDefault();
			e.returnValue = '';
		}
	});

	function setBusy(on) {
		busy = on;
		inputs.forEach(function (input) { input.disabled = on || !cfg.configured; });
		keepOpen.hidden = !on;
	}

	function report(text, percent, state) {
		status.hidden = false;
		status.classList.toggle('is-error', state === 'error');
		status.classList.toggle('is-done', state === 'done');
		message.textContent = text;
		bar.hidden = percent === null;
		if (percent !== null) {
			bar.value = percent;
		}
	}

	function post(action, fields) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		body.append('post_id', cfg.postId);
		Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
		return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) {
					throw new Error((json.data && json.data.message) || 'The server refused the request.');
				}
				return json.data;
			});
	}

	function upload(kind, ext, blob, onProgress) {
		return post('hcp_videos_upload_url', { kind: kind, ext: ext }).then(function (target) {
			return new Promise(function (resolve, reject) {
				var xhr = new XMLHttpRequest();
				xhr.open('PUT', target.uploadUrl);
				xhr.setRequestHeader('Content-Type', target.contentType);
				xhr.setRequestHeader('Cache-Control', target.cacheControl);
				xhr.upload.onprogress = function (e) {
					if (e.lengthComputable && onProgress) {
						onProgress(Math.round((e.loaded / e.total) * 100));
					}
				};
				xhr.onload = function () {
					if (xhr.status >= 200 && xhr.status < 300) {
						resolve(target.publicUrl);
					} else {
						reject(new Error('Upload failed (' + xhr.status + ').'));
					}
				};
				xhr.onerror = function () { reject(new Error('Upload failed: network error.')); };
				xhr.send(blob);
			});
		});
	}

	function convert(file) {
		return new Promise(function (resolve, reject) {
			var worker = new Worker(cfg.workerUrl, { type: 'module' });
			worker.onmessage = function (e) {
				var d = e.data;
				if (d.type === 'progress') {
					report('Converting video for the web… ' + Math.round(d.value * 100) + '%', d.value * 100);
				} else if (d.type === 'done') {
					worker.terminate();
					resolve(d);
				} else if (d.type === 'error') {
					worker.terminate();
					reject(new Error(d.message));
				}
			};
			worker.onerror = function () {
				worker.terminate();
				reject(new Error('The converter could not start. Try Chrome or Edge.'));
			};
			worker.postMessage({ file: file, libraryUrl: cfg.libraryUrl });
		});
	}

	function frameBlob(video) {
		var canvas = document.createElement('canvas');
		canvas.width = video.videoWidth;
		canvas.height = video.videoHeight;
		canvas.getContext('2d').drawImage(video, 0, 0);
		return new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', 0.85); });
	}

	function savePoster(blob, ext) {
		report('Uploading poster…', 0);
		return upload('poster', ext, blob, function (p) { report('Uploading poster… ' + p + '%', p); })
			.then(function (url) {
				return post('hcp_videos_save_file', { poster: url }).then(function () {
					preview.poster = url;
					posterImg.src = url;
					posterImg.hidden = false;
					posterNone.hidden = true;
					return url;
				});
			});
	}

	// Waits for the preview to settle on a frame that can be drawn.
	function seekTo(video, time) {
		return new Promise(function (resolve) {
			video.addEventListener('seeked', function done() {
				video.removeEventListener('seeked', done);
				resolve();
			});
			video.currentTime = time;
		});
	}

	function fail(err) {
		setBusy(false);
		document.title = pageTitle;
		report(err.message, null, 'error');
	}

	box.querySelector('.hcp-vf-video-input').addEventListener('change', function (e) {
		var file = e.target.files[0];
		e.target.value = '';
		if (!file) {
			return;
		}
		setBusy(true);
		report('Reading video…', 0);

		var result;
		convert(file)
			.then(function (r) {
				result = r;
				var blob = new Blob([r.buffer], { type: 'video/mp4' });
				// Preview the converted file locally: it is what viewers will get,
				// and a local file can be drawn to a canvas for the poster.
				preview.removeAttribute('poster');
				preview.src = URL.createObjectURL(blob);
				return upload('video', 'mp4', blob, function (p) { report('Uploading… ' + p + '%', p); });
			})
			.then(function (url) {
				report('Saving…', null);
				return post('hcp_videos_save_file', { src: url, duration: result.duration }).then(function (saved) {
					current.textContent = 'Current file: ' + url.split('/').pop() + (saved.duration ? ' · ' + saved.duration : '');
				});
			})
			.then(function () {
				return seekTo(preview, Math.min(2, (result.duration || 4) / 2));
			})
			.then(function () { return frameBlob(preview); })
			.then(function (blob) { return savePoster(blob, 'jpg'); })
			.then(function () {
				frameButton.disabled = false;
				setBusy(false);
				document.title = '✓ Video ready – ' + pageTitle;
				report('Video ready (' + result.width + '×' + result.height + '). It is live on the site now. Pick a different poster frame below if you like.', null, 'done');
			})
			.catch(fail);
	});

	frameButton.addEventListener('click', function () {
		setBusy(true);
		frameBlob(preview)
			.then(function (blob) { return savePoster(blob, 'jpg'); })
			.then(function () {
				setBusy(false);
				report('Poster updated.', null, 'done');
			})
			.catch(fail);
	});

	box.querySelector('.hcp-vf-poster-input').addEventListener('change', function (e) {
		var file = e.target.files[0];
		e.target.value = '';
		if (!file) {
			return;
		}
		var ext = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }[file.type];
		if (!ext) {
			report('Please choose a JPG, PNG or WebP image.', null, 'error');
			return;
		}
		setBusy(true);
		savePoster(file, ext)
			.then(function () {
				setBusy(false);
				report('Poster updated.', null, 'done');
			})
			.catch(fail);
	});

	box.querySelector('.hcp-vf-captions-input').addEventListener('change', function (e) {
		var file = e.target.files[0];
		e.target.value = '';
		if (!file) {
			return;
		}
		setBusy(true);
		file.text()
			.then(function (text) { return post('hcp_videos_save_file', { captions: text }); })
			.then(function () {
				setBusy(false);
				captionsState.textContent = 'Added';
				captionsRemove.hidden = false;
				report('Captions saved.', null, 'done');
			})
			.catch(fail);
	});

	captionsRemove.addEventListener('click', function () {
		setBusy(true);
		post('hcp_videos_save_file', { captions: '' })
			.then(function () {
				setBusy(false);
				captionsState.textContent = 'None';
				captionsRemove.hidden = true;
				report('Captions removed.', null, 'done');
			})
			.catch(fail);
	});

	// End-card preview over the preview player, following the End card fields
	// as they are edited (the fields belong to the separate ACF "End card" box).
	var cardShown = false;

	function acfValue(key) {
		var el = document.querySelector('[name="acf[' + key + ']"]');
		return el ? el.value.trim() : '';
	}

	function currentCard() {
		var mode = acfValue('field_hcpvid_endcard_mode') || 'auto';
		if (mode === 'off') {
			return null;
		}
		if (mode === 'custom') {
			return {
				heading: acfValue('field_hcpvid_endcard_heading'),
				title: acfValue('field_hcpvid_endcard_title'),
				url: acfValue('field_hcpvid_endcard_url') || '#',
				label: acfValue('field_hcpvid_endcard_label') || 'Find out more',
				thumb: ''
			};
		}
		return cfg.autoCard;
	}

	function renderCard() {
		var card = currentCard();
		window.hcpEndcard.remove(preview);
		if (!cardShown) {
			cardToggle.textContent = 'Preview end card';
			return;
		}
		if (!card) {
			cardToggle.textContent = 'No end card for this video';
			return;
		}
		preview.pause();
		window.hcpEndcard.show(preview, card);
		cardToggle.textContent = 'Hide end card preview';
	}

	cardToggle.addEventListener('click', function () {
		cardShown = !cardShown || !currentCard();
		renderCard();
	});

	document.addEventListener('input', function (e) {
		if (e.target.name && e.target.name.indexOf('acf[field_hcpvid_endcard_') === 0) {
			cardShown = true;
			renderCard();
		}
	});
	document.addEventListener('change', function (e) {
		if (e.target.name && e.target.name.indexOf('acf[field_hcpvid_endcard_') === 0) {
			cardShown = true;
			renderCard();
		}
	});

	preview.addEventListener('play', function () {
		cardShown = false;
		renderCard();
	});

	// The preview card is for looking at: its link must not leave the editor.
	box.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('a[data-hcp-endcard-link]')) {
			e.preventDefault();
		}
	});

	setBusy(false);
})();
