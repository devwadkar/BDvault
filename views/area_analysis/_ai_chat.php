<?php
/**
 * Reusable AI chat FAB + slide-out panel for an Area Statement.
 *
 * Usage: `<?php $__aaChatSid = (int)$statement['id']; require __DIR__ . '/_ai_chat.php'; ?>`
 * before the closing </body>. The parent view's $statement or $s must be in scope.
 *
 * The FAB sits fixed bottom-right. Click → panel slides in from the right, filling
 * ~460px width. Suggested prompts on empty state, threaded history, citation chips,
 * "not grounded" warning bar if the model didn't cite a DCPR clause.
 */
$__aaChatSid = $__aaChatSid ?? (isset($statement) ? (int)$statement['id'] : (isset($s) ? (int)$s['id'] : 0));
if ($__aaChatSid <= 0) return;
$__aaChatTitle = isset($statement) ? ($statement['statement_name'] ?? 'Area Statement')
                : (isset($s) ? ($s['statement_name'] ?? 'Area Statement') : 'Area Statement');
?>
<style>
.aa-chat-fab {
    position: fixed; bottom: 22px; right: 22px; z-index: 9998;
    width: 56px; height: 56px; border-radius: 50%; border: 0; cursor: pointer;
    background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff;
    box-shadow: 0 10px 30px rgba(79,70,229,0.45);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; transition: transform 0.15s;
}
.aa-chat-fab:hover { transform: translateY(-2px) scale(1.05); }
.aa-chat-fab .badge-new {
    position: absolute; top: -4px; right: -4px; width: 10px; height: 10px;
    background: #f59e0b; border-radius: 50%; border: 2px solid #fff;
}

.aa-chat-panel {
    position: fixed; bottom: 22px; right: 22px; z-index: 9999;
    width: 480px; max-width: calc(100vw - 44px);
    height: 640px; max-height: calc(100vh - 44px);
    background: #fff; border-radius: 16px;
    box-shadow: 0 20px 60px rgba(15,23,42,0.35);
    display: none; flex-direction: column; overflow: hidden;
    font-family: 'Lexend', system-ui, sans-serif;
}
.aa-chat-panel.open { display: flex; }

.aa-chat-hd {
    padding: 14px 18px; background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;
    display: flex; justify-content: space-between; align-items: center;
}
.aa-chat-hd h6 { margin: 0; font-weight: 700; font-size: 0.92rem; display: flex; align-items: center; gap: 8px; }
.aa-chat-hd .sub { color: #94a3b8; font-size: 0.7rem; margin-top: 2px; font-weight: 400; }
.aa-chat-hd .actions { display: flex; gap: 6px; }
.aa-chat-hd .btn-i {
    background: rgba(255,255,255,0.1); border: 0; color: #fff;
    width: 30px; height: 30px; border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.aa-chat-hd .btn-i:hover { background: rgba(255,255,255,0.2); }

.aa-chat-body {
    flex: 1; overflow-y: auto; padding: 18px; background: #f8fafc;
    display: flex; flex-direction: column; gap: 12px;
}

.aa-chat-msg { display: flex; gap: 10px; }
.aa-chat-msg.user { justify-content: flex-end; }
.aa-chat-msg .bubble {
    max-width: 78%; padding: 10px 14px; border-radius: 12px;
    font-size: 0.86rem; line-height: 1.5;
}
.aa-chat-msg.user .bubble { background: #4f46e5; color: #fff; border-bottom-right-radius: 4px; }
.aa-chat-msg.assistant .bubble { background: #fff; color: #0f172a; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }
.aa-chat-msg .bubble p { margin: 0 0 6px; }
.aa-chat-msg .bubble p:last-child { margin: 0; }
.aa-chat-msg .bubble ul, .aa-chat-msg .bubble ol { margin: 6px 0 6px 20px; padding: 0; }
.aa-chat-msg .bubble strong { font-weight: 700; }

.aa-chat-cites {
    display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px;
    padding-top: 8px; border-top: 1px dashed #cbd5e1;
}
.aa-chat-cite {
    background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 999px;
    font-size: 0.66rem; font-weight: 700; letter-spacing: 0.02em;
    font-family: 'JetBrains Mono', Consolas, monospace;
}
.aa-chat-cite.calc { background: #eef2ff; color: #4338ca; }
.aa-chat-warn {
    background: #fef3c7; color: #78350f; padding: 8px 12px; border-radius: 6px;
    font-size: 0.72rem; margin-top: 8px; display: flex; gap: 6px; align-items: flex-start;
    border-left: 3px solid #f59e0b;
}

.aa-chat-empty { text-align: center; color: #64748b; font-size: 0.85rem; padding: 24px 12px; }
.aa-chat-empty i { font-size: 2.2rem; color: #a5b4fc; }
.aa-chat-suggestions { display: flex; flex-direction: column; gap: 6px; margin-top: 14px; text-align: left; }
.aa-chat-sug {
    background: #fff; border: 1px solid #cbd5e1; padding: 9px 12px;
    border-radius: 8px; font-size: 0.82rem; cursor: pointer; color: #0f172a;
    text-align: left; transition: all 0.12s;
}
.aa-chat-sug:hover { border-color: #4f46e5; background: #eef2ff; color: #4338ca; }

.aa-chat-typing { color: #64748b; font-size: 0.8rem; font-style: italic; padding: 4px 8px; }
.aa-chat-typing::after { content: '…'; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }

.aa-chat-input-wrap {
    padding: 12px 14px; background: #fff; border-top: 1px solid #e2e8f0;
    display: flex; gap: 8px; align-items: flex-end;
}
.aa-chat-input {
    flex: 1; resize: none; border: 1px solid #cbd5e1; border-radius: 10px;
    padding: 8px 12px; font-size: 0.86rem; font-family: inherit;
    min-height: 40px; max-height: 120px;
}
.aa-chat-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
.aa-chat-send {
    background: #4f46e5; color: #fff; border: 0; width: 40px; height: 40px;
    border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.aa-chat-send:hover { background: #4338ca; }
.aa-chat-send:disabled { background: #cbd5e1; cursor: not-allowed; }
@media print {
    .aa-chat-fab, .aa-chat-panel { display: none !important; }
}
</style>

<button class="aa-chat-fab" onclick="aaChatToggle()" title="Ask the AI about this Area Statement" aria-label="Open AI assistant">
    <i class="bi bi-chat-dots-fill"></i>
    <span class="badge-new" id="aaChatBadge"></span>
</button>

<div class="aa-chat-panel" id="aaChatPanel">
    <div class="aa-chat-hd">
        <div>
            <h6><i class="bi bi-stars"></i>Statement Assistant</h6>
            <div class="sub"><?= htmlspecialchars($__aaChatTitle, ENT_QUOTES) ?></div>
        </div>
        <div class="actions">
            <button class="btn-i" onclick="aaChatClear()" title="Clear conversation"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button class="btn-i" onclick="aaChatToggle()" title="Close"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
    <div class="aa-chat-body" id="aaChatBody">
        <div class="aa-chat-empty" id="aaChatEmpty">
            <i class="bi bi-lightbulb"></i>
            <div class="mt-2">Ask about this Area Statement — grounded in every calc row + regulation.</div>
            <div class="aa-chat-suggestions">
                <button class="aa-chat-sug" onclick="aaChatSug(this)">How is Total BUA Approved computed?</button>
                <button class="aa-chat-sug" onclick="aaChatSug(this)">Why is my FSI at the ceiling?</button>
                <button class="aa-chat-sug" onclick="aaChatSug(this)">Are there any active manual overrides?</button>
                <button class="aa-chat-sug" onclick="aaChatSug(this)">Any open discrepancies I should worry about?</button>
                <button class="aa-chat-sug" onclick="aaChatSug(this)">What DCPR clauses apply to this scheme?</button>
            </div>
        </div>
    </div>
    <div class="aa-chat-input-wrap">
        <textarea id="aaChatInput" class="aa-chat-input" placeholder="Ask about this statement…" rows="1"
                  onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();aaChatSend();}"
                  oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px';"></textarea>
        <button class="aa-chat-send" onclick="aaChatSend()" id="aaChatSendBtn" title="Send"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

<script>
(function() {
    const STATEMENT_ID = <?= (int)$__aaChatSid ?>;
    const history = [];
    const panel = document.getElementById('aaChatPanel');
    const body  = document.getElementById('aaChatBody');
    const empty = document.getElementById('aaChatEmpty');
    const input = document.getElementById('aaChatInput');
    const sendBtn = document.getElementById('aaChatSendBtn');

    window.aaChatToggle = () => panel.classList.toggle('open');
    window.aaChatClear = () => {
        if (!confirm('Clear this conversation?')) return;
        history.length = 0;
        body.innerHTML = '';
        body.appendChild(empty);
    };
    window.aaChatSug = (btn) => {
        input.value = btn.textContent;
        aaChatSend();
    };
    window.aaChatSend = async () => {
        const q = input.value.trim();
        if (!q) return;
        if (empty.parentNode) empty.remove();

        appendMsg('user', escapeHtml(q));
        history.push({role: 'user', text: q});
        input.value = '';
        input.style.height = '40px';
        sendBtn.disabled = true;

        const typing = document.createElement('div');
        typing.className = 'aa-chat-typing';
        typing.textContent = 'Thinking';
        body.appendChild(typing);
        body.scrollTop = body.scrollHeight;

        try {
            const res = await fetch('index.php?page=api_area_assistant', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({statement_id: STATEMENT_ID, question: q, history})
            });
            const data = await res.json();
            typing.remove();
            if (!data.success) {
                appendMsg('assistant', `<em>Error:</em> ${escapeHtml(data.error || 'Unknown')}`);
            } else {
                const bubble = appendMsg('assistant', formatAnswer(data.answer));
                if ((data.citations || []).length || (data.calc_mentions || []).length) {
                    const chips = document.createElement('div');
                    chips.className = 'aa-chat-cites';
                    (data.citations || []).forEach(c => {
                        const chip = document.createElement('span');
                        chip.className = 'aa-chat-cite';
                        chip.textContent = c;
                        chips.appendChild(chip);
                    });
                    (data.calc_mentions || []).forEach(c => {
                        const chip = document.createElement('span');
                        chip.className = 'aa-chat-cite calc';
                        chip.textContent = c;
                        chips.appendChild(chip);
                    });
                    bubble.appendChild(chips);
                }
                (data.warnings || []).forEach(w => {
                    const warn = document.createElement('div');
                    warn.className = 'aa-chat-warn';
                    warn.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><div>' + escapeHtml(w) + '</div>';
                    bubble.appendChild(warn);
                });
                history.push({role: 'assistant', text: data.answer});
            }
        } catch (e) {
            typing.remove();
            appendMsg('assistant', `<em>Network error:</em> ${escapeHtml(e.message)}`);
        }
        sendBtn.disabled = false;
        body.scrollTop = body.scrollHeight;
    };

    function appendMsg(role, contentHtml) {
        const row = document.createElement('div');
        row.className = 'aa-chat-msg ' + role;
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.innerHTML = contentHtml;
        row.appendChild(bubble);
        body.appendChild(row);
        body.scrollTop = body.scrollHeight;
        return bubble;
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function formatAnswer(text) {
        // Lightweight markdown: **bold**, bullet lines, paragraph breaks
        let out = escapeHtml(text)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;font-family:JetBrains Mono,monospace;font-size:0.82em;">$1</code>');
        const paras = out.split(/\n\n+/).map(p => {
            const lines = p.split('\n');
            if (lines.every(l => /^\s*[-*]\s/.test(l))) {
                return '<ul>' + lines.map(l => '<li>' + l.replace(/^\s*[-*]\s/, '') + '</li>').join('') + '</ul>';
            }
            return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
        });
        return paras.join('');
    }
})();
</script>
