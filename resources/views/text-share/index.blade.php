<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Text Share</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='18' cy='5' r='3'/><circle cx='6' cy='12' r='3'/><circle cx='18' cy='19' r='3'/><line x1='8.59' y1='13.51' x2='15.42' y2='17.49'/><line x1='15.41' y1='6.51' x2='8.59' y2='10.49'/></svg>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pako/2.1.0/pako.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lz-string/1.5.0/lz-string.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" id="hljs-dark">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css" id="hljs-light" disabled>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="/js/auth.js"></script>
    <script src="/js/auth-patch.js"></script>
    <link rel="stylesheet" href="/css/text-share.css">
</head>
<body>
    <div class="header">
        <div class="url-container">
            <div class="url-display" id="urlDisplay" onclick="selectUrl()">-</div>
            <button class="url-copy-btn" onclick="copyUrl()">Copy</button>
        </div>
        <select class="format-select" id="formatSelect" onchange="handleFormatChange()">
            <option value="auto">Auto Detect</option>
            <option value="plain">Plain Text</option>
            <option value="markdown">Markdown</option>
            <option value="json">JSON</option>
            <option value="xml">XML</option>
            <option value="html">HTML</option>
            <option value="base64-encode">Base64 Encode</option>
            <option value="base64-decode">Base64 Decode</option>
        </select>
        <button class="btn" onclick="formatEditor()">Format</button>
        <button class="btn" onclick="toggleTheme()">◐</button>
        

        <div class="save-options">
            <input type="password" id="sharePassword" class="format-select" placeholder="Password (optional)" style="min-width: 150px;">
            <select id="expiresIn" class="format-select">
                <option value="1day">1 Day</option>
                <option value="1week">1 Week</option>
                <option value="1month" selected>1 Month</option>
                <option value="1year">1 Year</option>
            </select>
            <button class="btn" onclick="saveToDatabase()">Save</button>
            <button class="btn" onclick="toggleHistory()" id="historyBtn">History</button>
            <div id="authSection"></div>
        </div>
    </div>
    
    <div class="main">
        <div class="panel editor-panel">
            <div class="panel-header">
                <span>Editor</span>
            </div>
            <textarea 
                class="editor" 
                id="editor" 
                placeholder="Start typing or paste JSON, XML, HTML, Markdown..."
                spellcheck="false"
            ></textarea>
        </div>
        <div class="panel">
            <div class="panel-header">
                <span>Preview</span>
                <div class="panel-actions">
                    <button class="panel-btn" onclick="expandAll()">Expand All</button>
                    <button class="panel-btn" onclick="collapseAll()">Collapse All</button>
                </div>
            </div>
            <div class="preview" id="preview"></div>
        </div>
    </div>
    
    <div class="status">
        <span id="charCount">0 chars</span>
        <span id="compressionInfo">-</span>
    </div>

    <div class="toast" id="toast">Copied!</div>

    <!-- History Sidebar -->
    <div class="history-overlay" id="historyOverlay" onclick="toggleHistory()"></div>
    <div class="history-sidebar" id="historySidebar">
        <div class="history-header">
            <h2>📋 Your History</h2>
            <button class="history-close" onclick="toggleHistory()">✕</button>
        </div>
        <div class="history-list" id="historyList">
            <div class="history-empty">No shares yet. Create your first share!</div>
        </div>
    </div>

    <script src="/js/text-share/app.js"></script>
    @include('text-share.partials.auth-modal')
</body>
</html>