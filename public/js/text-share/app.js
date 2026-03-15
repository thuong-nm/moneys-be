        const editor = document.getElementById('editor');
        const urlDisplay = document.getElementById('urlDisplay');
        const preview = document.getElementById('preview');
        const formatSelect = document.getElementById('formatSelect');

        let savedHashId = null;
        let browserId = null;

        marked.setOptions({ breaks: true, gfm: true });

        // ===== BROWSER FINGERPRINT =====
        function generateBrowserId() {
            // Check if already stored in localStorage
            let stored = localStorage.getItem('ts_browser_id');
            if (stored) return stored;

            // Generate simple fingerprint from browser properties
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('TextShare', 0, 0);
            const canvasData = canvas.toDataURL();

            const fingerprint = [
                navigator.userAgent,
                navigator.language,
                screen.colorDepth,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                canvasData.substring(0, 100), // First 100 chars of canvas data
            ].join('|');

            // Hash the fingerprint using simple hash function
            let hash = 0;
            for (let i = 0; i < fingerprint.length; i++) {
                const char = fingerprint.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32bit integer
            }

            const browserId = 'br_' + Math.abs(hash).toString(36) + '_' + Date.now().toString(36);

            // Store in localStorage
            localStorage.setItem('ts_browser_id', browserId);
            return browserId;
        }

        // ===== COMPRESSION =====
        function compressRaw(text) { return 'R' + encodeURIComponent(text); }
        function compressLZ(text) { return 'L' + LZString.compressToEncodedURIComponent(text); }
        function compressPako(text) {
            const compressed = pako.deflate(new TextEncoder().encode(text), { level: 9 });
            const base64 = btoa(String.fromCharCode(...compressed));
            return 'Z' + base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }
        function compress(text) {
            if (!text) return '';
            const results = [compressRaw(text), compressLZ(text), compressPako(text)];
            return results.reduce((a, b) => a.length <= b.length ? a : b);
        }
        function decompress(data) {
            if (!data) return '';
            const prefix = data[0];
            try {
                switch(prefix) {
                    case 'R': return decodeURIComponent(data.slice(1));
                    case 'L': return LZString.decompressFromEncodedURIComponent(data.slice(1)) || '';
                    case 'Z': case 'P': {
                        let base64 = data.slice(1).replace(/-/g, '+').replace(/_/g, '/');
                        while (base64.length % 4) base64 += '=';
                        const binary = atob(base64);
                        const bytes = new Uint8Array(binary.length);
                        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
                        return new TextDecoder().decode(pako.inflate(bytes));
                    }
                    default:
                        const lzResult = LZString.decompressFromEncodedURIComponent(data);
                        if (lzResult) return lzResult;
                        return decodeURIComponent(data);
                }
            } catch(e) { return null; }
        }
        
        // ===== THEME =====
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            document.getElementById('hljs-dark').disabled = (next === 'light');
            document.getElementById('hljs-light').disabled = (next === 'dark');
            updatePreview();
        }
        function loadTheme() {
            const saved = localStorage.getItem('theme') || 'dark';
            if (saved === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
                document.getElementById('hljs-dark').disabled = true;
                document.getElementById('hljs-light').disabled = false;
            }
        }
        
        // ===== FORMAT DETECTION =====
        function detectFormat(text) {
            const trimmed = text.trim();
            if ((trimmed.startsWith('{') && trimmed.endsWith('}')) ||
                (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
                try { JSON.parse(trimmed); return 'json'; } catch(e) {}
            }
            if (trimmed.startsWith('<?xml') || 
                (trimmed.toLowerCase().includes('<!doctype html')) ||
                (trimmed.toLowerCase().startsWith('<html'))) {
                return 'html';
            }
            if (trimmed.startsWith('<') && trimmed.includes('</')) {
                return 'xml';
            }
            if (/^#{1,6}\s/m.test(trimmed) || /\*\*.*\*\*/.test(trimmed) ||
                /^[-*+]\s/m.test(trimmed) || /```/.test(trimmed) ||
                /\[.*\]\(.*\)/.test(trimmed)) {
                return 'markdown';
            }
            return 'plain';
        }
        
        function getActiveFormat() {
            const selected = formatSelect.value;
            if (selected === 'auto') {
                return detectFormat(editor.value);
            }
            // Skip encode/decode options
            if (selected === 'base64-encode' || selected === 'base64-decode') {
                return 'base64';
            }
            return selected;
        }

        function handleFormatChange() {
            const selected = formatSelect.value;

            // Handle Base64 Encode
            if (selected === 'base64-encode') {
                const text = editor.value;
                if (!text) {
                    showToast('Nothing to encode!');
                    formatSelect.value = 'auto';
                    return;
                }

                try {
                    const encoded = encodeBase64(text);
                    editor.value = encoded;
                    formatSelect.value = 'base64';
                    updateUrl();
                    showToast('Encoded to Base64!');
                } catch(e) {
                    showToast('Error encoding: ' + e.message);
                    formatSelect.value = 'auto';
                }
                return;
            }

            // Handle Base64 Decode
            if (selected === 'base64-decode') {
                const text = editor.value.trim();
                if (!text) {
                    showToast('Nothing to decode!');
                    formatSelect.value = 'auto';
                    return;
                }

                try {
                    const decoded = decodeBase64(text);
                    if (decoded === 'Invalid Base64') {
                        showToast('Invalid Base64 string!');
                        formatSelect.value = 'auto';
                        return;
                    }
                    editor.value = decoded;
                    formatSelect.value = 'auto';
                    updateUrl();
                    showToast('Decoded from Base64!');
                } catch(e) {
                    showToast('Error decoding: ' + e.message);
                    formatSelect.value = 'auto';
                }
                return;
            }

            // Normal format change - update preview
            updatePreview();
        }
        
        // ===== JSON TREE =====
        function renderJSONTree(data, isLast = true) {
            if (data === null) return `<span class="tree-null">null</span>${isLast ? '' : '<span class="tree-comma">,</span>'}`;
            if (typeof data === 'boolean') return `<span class="tree-bool">${data}</span>${isLast ? '' : '<span class="tree-comma">,</span>'}`;
            if (typeof data === 'number') return `<span class="tree-number">${data}</span>${isLast ? '' : '<span class="tree-comma">,</span>'}`;
            if (typeof data === 'string') return `<span class="tree-string">"${escapeHtml(data)}"</span>${isLast ? '' : '<span class="tree-comma">,</span>'}`;
            
            if (Array.isArray(data)) {
                if (data.length === 0) return `<span class="tree-bracket">[]</span>${isLast ? '' : '<span class="tree-comma">,</span>'}`;
                
                let html = `<div class="tree-item">
                    <div class="tree-line">
                        <span class="tree-toggle" onclick="toggleTree(this)">▼</span>
                        <span class="tree-content">
                            <span class="tree-bracket">[</span>
                            <span class="tree-ellipsis" style="display:none" onclick="toggleTree(this.parentElement.parentElement.previousElementSibling.querySelector('.tree-toggle'))">${data.length} items...</span>
                        </span>
                    </div>
                    <div class="tree-children">`;
                
                data.forEach((item, i) => {
                    html += renderJSONTree(item, i === data.length - 1);
                });
                
                html += `</div>
                    <div class="tree-line"><span class="tree-bracket">]</span>${isLast ? '' : '<span class="tree-comma">,</span>'}</div>
                </div>`;
                return html;
            }
            
            if (typeof data === 'object') {
                const keys = Object.keys(data);
                if (keys.length === 0) return `<span class="tree-bracket">{}</span>${isLast ? '' : '<span class="tree-comma">,</span>'}`;
                
                let html = `<div class="tree-item">
                    <div class="tree-line">
                        <span class="tree-toggle" onclick="toggleTree(this)">▼</span>
                        <span class="tree-content">
                            <span class="tree-bracket">{</span>
                            <span class="tree-ellipsis" style="display:none" onclick="toggleTree(this.parentElement.parentElement.previousElementSibling.querySelector('.tree-toggle'))">${keys.length} keys...</span>
                        </span>
                    </div>
                    <div class="tree-children">`;
                
                keys.forEach((key, i) => {
                    const isLastKey = i === keys.length - 1;
                    const value = data[key];
                    const isExpandable = (typeof value === 'object' && value !== null);
                    
                    if (isExpandable) {
                        html += `<div class="tree-item">
                            <div class="tree-line">
                                <span class="tree-toggle" onclick="toggleTree(this)">▼</span>
                                <span class="tree-content">
                                    <span class="tree-key">"${escapeHtml(key)}"</span>: `;
                        
                        if (Array.isArray(value)) {
                            html += `<span class="tree-bracket">[</span>
                                <span class="tree-ellipsis" style="display:none" onclick="toggleTree(this.parentElement.parentElement.previousElementSibling.querySelector('.tree-toggle'))">${value.length} items...</span>`;
                        } else {
                            html += `<span class="tree-bracket">{</span>
                                <span class="tree-ellipsis" style="display:none" onclick="toggleTree(this.parentElement.parentElement.previousElementSibling.querySelector('.tree-toggle'))">${Object.keys(value).length} keys...</span>`;
                        }
                        
                        html += `</span></div><div class="tree-children">`;
                        
                        if (Array.isArray(value)) {
                            value.forEach((item, j) => {
                                html += renderJSONTree(item, j === value.length - 1);
                            });
                            html += `</div><div class="tree-line"><span class="tree-bracket">]</span>${isLastKey ? '' : '<span class="tree-comma">,</span>'}</div></div>`;
                        } else {
                            const subKeys = Object.keys(value);
                            subKeys.forEach((subKey, j) => {
                                html += `<div class="tree-line"><span class="tree-toggle empty">▼</span><span class="tree-content"><span class="tree-key">"${escapeHtml(subKey)}"</span>: ${renderJSONTree(value[subKey], j === subKeys.length - 1)}</span></div>`;
                            });
                            html += `</div><div class="tree-line"><span class="tree-bracket">}</span>${isLastKey ? '' : '<span class="tree-comma">,</span>'}</div></div>`;
                        }
                    } else {
                        html += `<div class="tree-line">
                            <span class="tree-toggle empty">▼</span>
                            <span class="tree-content">
                                <span class="tree-key">"${escapeHtml(key)}"</span>: ${renderJSONTree(value, isLastKey)}
                            </span>
                        </div>`;
                    }
                });
                
                html += `</div>
                    <div class="tree-line"><span class="tree-bracket">}</span>${isLast ? '' : '<span class="tree-comma">,</span>'}</div>
                </div>`;
                return html;
            }
            
            return String(data);
        }
        
        // ===== XML/HTML TREE =====
        function renderXMLTree(xmlString) {
            const parser = new DOMParser();
            const format = getActiveFormat();
            const trimmed = xmlString.trim();
            
            // Check if it's a full HTML document or just a snippet
            const isFullHtml = trimmed.toLowerCase().includes('<!doctype') || 
                               trimmed.toLowerCase().includes('<html');
            
            if (format === 'xml' && !isFullHtml) {
                // Try XML first
                const doc = parser.parseFromString(xmlString, 'text/xml');
                const parseError = doc.querySelector('parsererror');
                if (!parseError) {
                    return `<div class="tree">${renderXMLNode(doc.documentElement)}</div>`;
                }
            }
            
            // Parse as HTML
            const doc = parser.parseFromString(xmlString, 'text/html');
            
            // For snippets, get content from body directly
            if (!isFullHtml) {
                const body = doc.body;
                if (body && body.childNodes.length > 0) {
                    let html = '<div class="tree">';
                    Array.from(body.childNodes).forEach(node => {
                        html += renderXMLNode(node);
                    });
                    html += '</div>';
                    return html;
                }
            }
            
            // For full HTML documents, render from html element
            return `<div class="tree">${renderXMLNode(doc.documentElement)}</div>`;
        }
        
        function renderXMLNode(node, depth = 0) {
            if (!node) return '';
            
            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.textContent.trim();
                if (!text) return '';
                return `<span class="tree-text">${escapeHtml(text)}</span>`;
            }
            
            if (node.nodeType === Node.COMMENT_NODE) {
                return `<div class="tree-line">
                    <span class="tree-toggle empty">▼</span>
                    <span class="tree-comment">&lt;!-- ${escapeHtml(node.textContent)} --&gt;</span>
                </div>`;
            }
            
            if (node.nodeType !== Node.ELEMENT_NODE) return '';
            
            const tagName = node.tagName.toLowerCase();
            const attrs = Array.from(node.attributes || []);
            const children = Array.from(node.childNodes).filter(n => 
                n.nodeType === Node.ELEMENT_NODE || 
                (n.nodeType === Node.TEXT_NODE && n.textContent.trim()) ||
                n.nodeType === Node.COMMENT_NODE
            );
            
            let attrStr = attrs.map(a => 
                ` <span class="tree-attr-name">${a.name}</span>=<span class="tree-attr-value">"${escapeHtml(a.value)}"</span>`
            ).join('');
            
            // Self-closing or empty
            if (children.length === 0) {
                return `<div class="tree-line">
                    <span class="tree-toggle empty">▼</span>
                    <span class="tree-content">
                        <span class="tree-tag">&lt;${tagName}</span>${attrStr}<span class="tree-tag">/&gt;</span>
                    </span>
                </div>`;
            }
            
            // Single text child
            if (children.length === 1 && children[0].nodeType === Node.TEXT_NODE) {
                return `<div class="tree-line">
                    <span class="tree-toggle empty">▼</span>
                    <span class="tree-content">
                        <span class="tree-tag">&lt;${tagName}</span>${attrStr}<span class="tree-tag">&gt;</span><span class="tree-text">${escapeHtml(children[0].textContent.trim())}</span><span class="tree-tag">&lt;/${tagName}&gt;</span>
                    </span>
                </div>`;
            }
            
            // Has element children - collapsible
            let html = `<div class="tree-item">
                <div class="tree-line">
                    <span class="tree-toggle" onclick="toggleTree(this)">▼</span>
                    <span class="tree-content">
                        <span class="tree-tag">&lt;${tagName}</span>${attrStr}<span class="tree-tag">&gt;</span>
                        <span class="tree-ellipsis" style="display:none" onclick="toggleTree(this.parentElement.parentElement.previousElementSibling.querySelector('.tree-toggle'))">...</span>
                    </span>
                </div>
                <div class="tree-children">`;
            
            children.forEach(child => {
                html += renderXMLNode(child, depth + 1);
            });
            
            html += `</div>
                <div class="tree-line">
                    <span class="tree-toggle empty">▼</span>
                    <span class="tree-tag">&lt;/${tagName}&gt;</span>
                </div>
            </div>`;
            
            return html;
        }
        
        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
        
        function toggleTree(el) {
            const item = el.closest('.tree-item');
            if (!item) return;
            
            const children = item.querySelector('.tree-children');
            const ellipsis = item.querySelector('.tree-ellipsis');
            
            if (children) {
                children.classList.toggle('collapsed');
                el.classList.toggle('collapsed');
                if (ellipsis) {
                    ellipsis.style.display = children.classList.contains('collapsed') ? 'inline' : 'none';
                }
            }
        }
        
        function expandAll() {
            preview.querySelectorAll('.tree-children.collapsed').forEach(el => el.classList.remove('collapsed'));
            preview.querySelectorAll('.tree-toggle.collapsed').forEach(el => el.classList.remove('collapsed'));
            preview.querySelectorAll('.tree-ellipsis').forEach(el => el.style.display = 'none');
        }
        
        function collapseAll() {
            preview.querySelectorAll('.tree-children').forEach(el => el.classList.add('collapsed'));
            preview.querySelectorAll('.tree-toggle:not(.empty)').forEach(el => el.classList.add('collapsed'));
            preview.querySelectorAll('.tree-ellipsis').forEach(el => el.style.display = 'inline');
        }
        
        // ===== FORMAT EDITOR =====
        function formatEditor() {
            const text = editor.value.trim();
            if (!text) return;
            
            const format = getActiveFormat();
            
            try {
                if (format === 'json') {
                    const obj = JSON.parse(text);
                    editor.value = JSON.stringify(obj, null, 2);
                    updateUrl();
                    showToast('Formatted!');
                } else if (format === 'xml' || format === 'html') {
                    editor.value = formatXML(text);
                    updateUrl();
                    showToast('Formatted!');
                } else {
                    showToast('Format not supported for this type');
                }
            } catch(e) {
                showToast('Invalid format: ' + e.message);
            }
        }
        
        function formatXML(text) {
            let formatted = '';
            let indent = 0;
            const lines = text.replace(/>\s*</g, '>\n<').split('\n');
            
            lines.forEach(line => {
                line = line.trim();
                if (!line) return;
                
                if (line.startsWith('</')) indent = Math.max(0, indent - 1);
                formatted += '  '.repeat(indent) + line + '\n';
                if (line.startsWith('<') && !line.startsWith('</') && !line.startsWith('<?') &&
                    !line.startsWith('<!') && !line.endsWith('/>') && !/<\/[^>]+>$/.test(line)) {
                    indent++;
                }
            });
            
            return formatted.trim();
        }
        
        // ===== BASE64 =====
        function encodeBase64(text) {
            try {
                return btoa(unescape(encodeURIComponent(text)));
            } catch(e) {
                return btoa(text);
            }
        }

        function decodeBase64(text) {
            try {
                return decodeURIComponent(escape(atob(text)));
            } catch(e) {
                try {
                    return atob(text);
                } catch(e2) {
                    return 'Invalid Base64';
                }
            }
        }

        // ===== PREVIEW =====
        function updatePreview() {
            const text = editor.value;
            const format = getActiveFormat();

            preview.className = 'preview';

            if (!text) {
                preview.innerHTML = '<span style="color: var(--text-dim)">Preview will appear here...</span>';
                return;
            }

            try {
                switch(format) {
                    case 'json':
                        const obj = JSON.parse(text);
                        preview.innerHTML = `<div class="tree">${renderJSONTree(obj)}</div>`;
                        break;

                    case 'xml':
                    case 'html':
                        preview.innerHTML = renderXMLTree(text);
                        break;

                    case 'base64':
                        const decoded = decodeBase64(text);
                        preview.innerHTML = `<div style="font-family: monospace; white-space: pre-wrap; word-break: break-all;">
                            <div style="color: var(--text-dim); margin-bottom: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Decoded:</div>
                            <div>` + escapeHtml(decoded) + `</div>
                        </div>`;
                        break;

                    case 'markdown':
                        preview.classList.add('markdown');
                        preview.innerHTML = marked.parse(text);
                        preview.querySelectorAll('pre code').forEach(block => hljs.highlightElement(block));
                        break;

                    default:
                        preview.textContent = text;
                }
            } catch(e) {
                preview.textContent = text;
            }
        }
        
        // ===== SAVE TO DATABASE =====
        async function saveToDatabase() {
            const text = editor.value;
            if (!text) {
                showToast('Nothing to save!');
                return;
            }

            const compressed = compress(text);
            const format = getActiveFormat();
            const expiresIn = document.getElementById('expiresIn').value;
            const password = document.getElementById('sharePassword').value;

            const payload = {
                content: compressed,
                format: format,
                expires_in: expiresIn,
                browser_id: browserId
            };

            if (password) {
                payload.password = password;
            }

            try {
                const response = await fetch('/api/text-share', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    savedHashId = data.hash_id;
                    urlDisplay.textContent = data.url;

                    // Update browser URL without reload
                    history.replaceState(null, '', `/s/${data.hash_id}`);

                    // Reload history
                    loadHistory();

                    // Copy URL
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(data.url)
                            .then(() => showToast('Saved! URL copied.'))
                            .catch(() => {
                                fallbackCopy(data.url);
                                showToast('Saved!');
                            });
                    } else {
                        fallbackCopy(data.url);
                        showToast('Saved!');
                    }
                } else {
                    showToast('Error saving: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error(e);
                showToast('Error saving!');
            }
        }

        // ===== URL =====
        function updateUrl() {
            const text = editor.value;

            if (!text) {
                history.replaceState(null, '', location.pathname);
                urlDisplay.textContent = location.href;
                document.getElementById('charCount').textContent = '0 chars';
                document.getElementById('compressionInfo').textContent = '-';
                updatePreview();
                return;
            }
            
            // No auto URL generation - only on Save
            urlDisplay.textContent = "Click Save to generate share URL";

            updatePreview();
            const chars = text.length;
            document.getElementById('charCount').textContent = `${chars.toLocaleString()} chars`;
            document.getElementById('compressionInfo').textContent = 'Save to get share URL';
        }

        async function loadFromUrl() {
            // Check if URL is /s/{hashId}
            const pathMatch = location.pathname.match(/^\/s\/([a-zA-Z0-9]+)$/);

            if (pathMatch) {
                const hashId = pathMatch[1];
                try {
                    const response = await fetch(`/s/${hashId}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();

                    if (data.success && data.content) {
                        const decoded = decompress(data.content);
                        if (decoded !== null) {
                            editor.value = decoded;
                            if (data.format && data.format !== 'auto') {
                                formatSelect.value = data.format;
                            }
                            savedHashId = hashId;
                            urlDisplay.textContent = location.href;
                            updatePreview();
                        }
                    }
                } catch (e) {
                    console.error(e);
                }
                return;
            }
        }
        
        function selectUrl() {
            const range = document.createRange();
            range.selectNodeContents(urlDisplay);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
        
        function copyUrl() {
            const url = location.href;

            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    .then(() => showToast('Copied!'))
                    .catch(() => fallbackCopy(url));
            } else {
                fallbackCopy(url);
            }
        }

        function fallbackCopy(text) {
            // Fallback method using temporary textarea
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showToast('Copied!');
                } else {
                    showToast('Failed to copy!');
                }
            } catch (err) {
                showToast('Failed to copy!');
            }

            document.body.removeChild(textarea);
        }
        
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2000);
        }

        // ===== HISTORY =====
        async function loadHistory() {
            try {
                const response = await fetch('/api/text-share/history', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ browser_id: browserId })
                });

                const data = await response.json();

                if (data.success && data.shares.length > 0) {
                    displayHistory(data.shares);
                }
            } catch (e) {
                console.error('Failed to load history:', e);
            }
        }

        function displayHistory(shares) {
            const historyList = document.getElementById('historyList');
            const historyCount = document.getElementById('historyCount');

            // Always update count
            // historyCount.textContent = shares.length;

            if (shares.length === 0) {
                historyList.innerHTML = '<div class="history-empty">No shares yet. Create your first share!</div>';
                return;
            }

            // Build history items HTML
            let html = '';
            shares.forEach(share => {
                const createdDate = new Date(share.created_at);
                const expiresDate = new Date(share.expires_at);
                const now = new Date();
                const timeLeft = Math.ceil((expiresDate - now) / (1000 * 60 * 60 * 24));

                html += `
                    <div class="history-item" onclick="window.location.href='${share.url}'">
                        <div class="history-item-info">
                            <div class="history-item-url">${share.url}</div>
                            <div class="history-item-meta">
                                Created: ${createdDate.toLocaleString()} •
                                Expires in ${timeLeft} day${timeLeft !== 1 ? 's' : ''}
                                ${share.is_password_protected ? ' • 🔒 Protected' : ''}
                            </div>
                        </div>
                        <div class="history-item-badge">${share.format || 'plain'}</div>
                    </div>
                `;
            });

            historyList.innerHTML = html;
        }

        function toggleHistory() {
            const overlay = document.getElementById('historyOverlay');
            const sidebar = document.getElementById('historySidebar');
            overlay.classList.toggle('show');
            sidebar.classList.toggle('show');
        }

        // ===== INIT =====
        loadTheme();
        browserId = generateBrowserId();
        loadFromUrl();
        loadHistory();
        editor.addEventListener('input', updateUrl);
        editor.focus();

        if (!editor.value) {
            preview.innerHTML = '<span style="color: var(--text-dim)">Preview will appear here...</span>';
        }

        // Expose functions to global scope for inline event handlers
        window.saveToDatabase = saveToDatabase;
        window.handleFormatChange = handleFormatChange;
        window.formatEditor = formatEditor;
        window.toggleTheme = toggleTheme;
        window.selectUrl = selectUrl;
        window.copyUrl = copyUrl;
        window.expandAll = expandAll;
        window.collapseAll = collapseAll;
        window.toggleTree = toggleTree;
