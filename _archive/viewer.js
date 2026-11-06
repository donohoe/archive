/*
 * Archive viewer: opens files from a folder listing in a modal instead of
 * navigating to the raw file. Images are fitted to the viewport (very tall
 * images scroll), text/PDF/HTML are framed, video plays inline, and anything
 * else shows its info with a link to the original. Enabled via
 * ARCHIVE_VIEWER in config.php; without JS the links still open the file.
 */
(function () {
	'use strict';

	var TYPES = {
		image: ['jpg', 'jpeg', 'png', 'gif', 'svg'],
		text:  ['txt', 'md', 'css', 'js'],
		frame: ['pdf', 'html'],
		video: ['mp4', 'mov']
	};
	var TYPE_LABELS = { image: 'Image', text: 'Text', frame: 'Document', video: 'Video', other: 'File' };

	// Tall images are shrunk to fit the viewport (e.g. phone screenshots)
	// unless that would make them narrower than this, in which case they fill
	// the width and scroll (e.g. full-page captures). The limit is the
	// smaller of a fixed width and a share of the viewer's width, so it
	// adapts on small screens. On a typical laptop this lands around 3:1.
	var MIN_FIT_WIDTH = 300;   // px
	var MIN_FIT_SHARE = 0.6;   // of the available width

	// Up to this height:width ratio an image always fits whole, whatever the
	// window size. Covers phone screenshots (19.5:9 ≈ 2.17, 21:9 ≈ 2.33, plus
	// slightly longer captures); only taller images use the width rule above.
	var ALWAYS_FIT_RATIO = 2.6;

	var list = document.getElementById('files');
	if (!list || typeof HTMLDialogElement !== 'function') {
		return;
	}

	var links = Array.prototype.slice.call(list.querySelectorAll('a[data-name]'));
	if (!links.length) {
		return;
	}

	function typeFor(ext) {
		for (var type in TYPES) {
			if (TYPES[type].indexOf(ext) !== -1) {
				return type;
			}
		}
		return 'other';
	}

	var items = links.map(function (a) {
		var ext = (a.dataset.ext || '').toLowerCase();
		return {
			link:     a,
			url:      a.getAttribute('href'),
			name:     a.dataset.name,
			ext:      ext,
			type:     typeFor(ext),
			size:     parseInt(a.dataset.size, 10) || 0,
			modified: a.dataset.modified || ''
		};
	});

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) node.className = className;
		if (text != null) node.textContent = text;
		return node;
	}

	function formatSize(bytes) {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
		return (bytes / 1048576).toFixed(1) + ' MB';
	}

	/* Build the dialog once */

	var dialog  = el('dialog', 'viewer');
	dialog.setAttribute('aria-label', 'File viewer');
	dialog.tabIndex = -1; // focus target on open, so no button looks pre-selected

	var main    = el('div', 'viewer-main');
	var stage   = el('div', 'viewer-stage');
	main.appendChild(stage);

	function toolButton(className, symbol, label) {
		var button = el('button', 'viewer-tool ' + className, symbol);
		button.type = 'button';
		button.setAttribute('aria-label', label);
		button.title = label;
		return button;
	}

	// Top-right toolbar: ‹ › ×
	var tools    = el('div', 'viewer-tools');
	var prevBtn  = toolButton('viewer-prev', '‹', 'Previous file');
	var nextBtn  = toolButton('viewer-next', '›', 'Next file');
	var closeBtn = toolButton('viewer-close', '×', 'Close viewer');
	tools.appendChild(prevBtn);
	tools.appendChild(nextBtn);
	tools.appendChild(closeBtn);

	var panel    = el('aside', 'viewer-panel');
	var title    = el('h2', 'viewer-title');
	var meta     = el('dl', 'viewer-meta');
	var openLink = el('a', 'viewer-link', 'Open original ↗');
	openLink.target = '_blank';
	openLink.rel = 'noopener';
	var count    = el('div', 'viewer-count');
	var fileList = el('ul', 'viewer-files');
	panel.appendChild(tools);
	panel.appendChild(title);
	panel.appendChild(meta);
	panel.appendChild(openLink);
	panel.appendChild(count);
	panel.appendChild(fileList);

	// Every file in this folder (folders excluded); built once, the active
	// one is highlighted in renderPanel(). Real #hash links, so a modified
	// click opens that file's viewer in a new tab.
	var fileLinks = items.map(function (item, index) {
		var li = el('li');
		var a  = el('a', null, item.name);
		a.href = '#' + encodeURIComponent(item.name);
		a.addEventListener('click', function (event) {
			if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}
			event.preventDefault();
			show(index);
		});
		li.appendChild(a);
		fileList.appendChild(li);
		return a;
	});

	dialog.appendChild(main);
	dialog.appendChild(panel);
	document.body.appendChild(dialog);

	var current = -1;
	var dimensionsRow = null;

	function addMeta(label, value) {
		var dt = el('dt', null, label);
		var dd = el('dd', null, value);
		meta.appendChild(dt);
		meta.appendChild(dd);
		return dd;
	}

	function setDimensions(w, h) {
		if (dimensionsRow && w && h) {
			dimensionsRow.textContent = w + ' × ' + h;
			dimensionsRow.hidden = false;
			dimensionsRow.previousSibling.hidden = false; // its <dt>
		}
	}

	// True while a media element is still the one being shown (a slow load
	// from a file the user already moved past must not update the panel)
	function isCurrent(node) {
		return node.parentNode === stage;
	}

	// Decide between "fit whole image" and "fit width, scroll" for an image
	function layoutImage(img) {
		var w = img.naturalWidth, h = img.naturalHeight;
		if (!w || !h) return; // e.g. SVG without intrinsic size: just fit
		if (h / w <= ALWAYS_FIT_RATIO) {
			stage.classList.remove('is-tall');
			return;
		}

		var style  = getComputedStyle(stage);
		var availW = stage.clientWidth  - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
		var availH = stage.clientHeight - parseFloat(style.paddingTop)  - parseFloat(style.paddingBottom);

		// Width the image would have if shrunk to fit entirely
		var fittedW = Math.min(w, availW, availH * (w / h));
		var minW    = Math.min(MIN_FIT_WIDTH, availW * MIN_FIT_SHARE);
		var tooTall = h * (fittedW / w) >= availH - 1 && fittedW < Math.min(minW, w);

		stage.classList.toggle('is-tall', tooTall);
	}

	function renderContent(item) {
		stage.textContent = '';
		stage.className = 'viewer-stage is-' + item.type;
		stage.scrollTop = 0;

		var node;
		switch (item.type) {
			case 'image':
				node = el('img');
				node.alt = item.name;
				node.addEventListener('load', function () {
					if (!isCurrent(node)) return;
					layoutImage(node);
					setDimensions(node.naturalWidth, node.naturalHeight);
				});
				node.src = item.url;
				break;
			case 'text':
			case 'frame':
				node = el('iframe');
				node.title = item.name;
				if (item.ext === 'html') {
					// Matches the server's sandbox header for archived HTML
					node.setAttribute('sandbox', 'allow-scripts allow-forms allow-popups allow-modals');
				}
				node.src = item.url;
				break;
			case 'video':
				node = el('video');
				node.controls = true;
				node.preload = 'metadata';
				node.addEventListener('loadedmetadata', function () {
					if (!isCurrent(node)) return;
					setDimensions(node.videoWidth, node.videoHeight);
				});
				node.src = item.url;
				break;
			default:
				node = el('div', 'viewer-none');
				node.appendChild(el('span', 'viewer-none-ext', item.ext));
				node.appendChild(el('span', null, 'Preview not available'));
		}
		stage.appendChild(node);
	}

	function renderPanel(item) {
		title.textContent = item.name;
		meta.textContent = '';
		addMeta('Type', TYPE_LABELS[item.type] + ' (.' + item.ext + ')');
		addMeta('Size', formatSize(item.size));
		dimensionsRow = addMeta('Dimensions', '');
		dimensionsRow.hidden = true;
		dimensionsRow.previousSibling.hidden = true;
		if (item.modified) {
			addMeta('Modified', item.modified);
		}
		openLink.href = item.url;

		var many = items.length > 1;
		prevBtn.hidden = nextBtn.hidden = !many;
		count.textContent = many ? (current + 1) + ' of ' + items.length : '';
		fileList.hidden = !many;

		fileLinks.forEach(function (a, index) {
			if (index === current) {
				a.setAttribute('aria-current', 'true');
			} else {
				a.removeAttribute('aria-current');
			}
		});
		if (many) {
			var active = fileLinks[current];
			// Keep the active file visible in a long list, without moving the page
			if (active.offsetTop < panel.scrollTop || active.offsetTop > panel.scrollTop + panel.clientHeight - 40) {
				panel.scrollTop = active.offsetTop - panel.clientHeight / 2;
			}
		}
	}

	function preload(index) {
		var item = items[(index + items.length) % items.length];
		if (item && item.type === 'image') {
			new Image().src = item.url;
		}
	}

	function show(index) {
		current = (index + items.length) % items.length;
		var item = items[current];
		renderContent(item);
		renderPanel(item);
		setHash(item.name);
		if (items.length > 1) {
			preload(current + 1);
			preload(current - 1);
		}
	}

	function open(index) {
		show(index);
		if (!dialog.open) {
			document.documentElement.classList.add('viewer-open');
			dialog.showModal();
			dialog.focus();
		}
	}

	function close() {
		if (dialog.open) {
			dialog.close();
		}
	}

	/* Deep link: #<filename> reopens the same file */

	function setHash(name) {
		try {
			history.replaceState(null, '', '#' + encodeURIComponent(name));
		} catch (e) {}
	}

	function clearHash() {
		try {
			history.replaceState(null, '', location.pathname + location.search);
		} catch (e) {}
	}

	function indexFromHash() {
		if (location.hash.length < 2) return -1;
		var name;
		try {
			name = decodeURIComponent(location.hash.slice(1));
		} catch (e) {
			return -1;
		}
		for (var i = 0; i < items.length; i++) {
			if (items[i].name === name) return i;
		}
		return -1;
	}

	/* Events */

	items.forEach(function (item, index) {
		item.link.addEventListener('click', function (event) {
			// Let modified clicks (new tab/window) behave normally
			if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}
			event.preventDefault();
			open(index);
		});
	});

	prevBtn.addEventListener('click', function () { show(current - 1); });
	nextBtn.addEventListener('click', function () { show(current + 1); });
	closeBtn.addEventListener('click', close);

	// Clicking empty space around the content closes the viewer
	stage.addEventListener('click', function (event) {
		if (event.target === stage) close();
	});

	// Re-decide fit vs. scroll when the window size changes
	var resizeTimer = null;
	window.addEventListener('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			var img = stage.querySelector('img');
			if (dialog.open && img && img.complete) layoutImage(img);
		}, 100);
	});

	dialog.addEventListener('keydown', function (event) {
		if (items.length < 2) return;
		if (event.key === 'ArrowLeft')  { event.preventDefault(); show(current - 1); }
		if (event.key === 'ArrowRight') { event.preventDefault(); show(current + 1); }
	});

	// Fires for Escape, the close button and close() alike
	dialog.addEventListener('close', function () {
		stage.textContent = ''; // stops video/audio playback
		document.documentElement.classList.remove('viewer-open');
		clearHash();
		var item = items[current];
		if (item) item.link.focus();
	});

	function openFromHash() {
		var index = indexFromHash();
		if (index !== -1) {
			open(index);
		}
	}

	// Typing or pasting a #filename into the address bar also opens it
	window.addEventListener('hashchange', openFromHash);
	openFromHash();
})();
