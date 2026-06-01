<?php
session_start();
set_time_limit(0);

// ================= PENGATURAN =================
$api_key = "sk-c1";
$PIN_RAHASIA = "123456"; // Ganti PIN sesuai keinginanmu
// ==============================================

// 1. SISTEM KEAMANAN (LOGIN)
if (isset($_POST['pin'])) {
    if ($_POST['pin'] === $PIN_RAHASIA) {
        $_SESSION['logged_in'] = true;
        header("Location: ?");
        exit;
    } else {
        $login_error = "❌ PIN Salah!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?");
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Login - DeepSeek Admin</title>';
    echo '<style>body{background:#1a1a1a;color:#fff;font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;} .login-box{background:#252525;padding:30px;border-radius:10px;text-align:center;box-shadow:0 0 15px rgba(0,0,0,0.5);} input{padding:10px;margin:10px 0;border:none;border-radius:5px;width:80%;text-align:center;} button{background:#007bff;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;}</style></head><body>';
    echo '<div class="login-box"><h2>🔒 Masukkan PIN</h2>';
    if (isset($login_error)) echo "<p style='color:#ff4d4d;'>$login_error</p>";
    echo '<form method="POST"><input type="password" name="pin" placeholder="PIN Rahasia" required autofocus><br><button type="submit">Masuk</button></form></div></body></html>';
    exit;
}

// 2. FITUR SIMPAN PESAN VIA POST (Solusi Anti Koneksi Terputus)
if (isset($_POST['save_msg'])) {
    if (!isset($_SESSION['chat_history'])) { $_SESSION['chat_history'] = []; }
    $_SESSION['chat_history'][] = ["role" => "user", "content" => $_POST['save_msg']];
    echo "OK";
    exit;
}

// 3. FITUR AUTO-TOOLS (HANYA MURNI BACA & CARI - DIKUNCI AMAN 100%)
if (isset($_GET['tool'])) {
    $tool = $_GET['tool'];
    // escapeshellarg memastikan input aman dari injeksi command berbahaya
    $arg = escapeshellarg($_GET['arg'] ?? ''); 
    
    if ($tool === 'cari') {
        // Mengabaikan folder log/cache/vendor agar Nginx tidak timeout dan membatasi 50 baris
        $cmd = "grep -rn --exclude-dir={node_modules,vendor,.git,cache,logs} " . $arg . " /var/www/streamify/ 2>&1 | head -n 50";
        $output = shell_exec($cmd);
        echo !empty($output) ? $output : "Tidak ada hasil pencarian untuk {$_GET['arg']}.";
    } elseif ($tool === 'baca') {
        // Hanya membaca isi file, dibatasi 300 baris
        $cmd = "cat " . $arg . " 2>&1 | head -n 300";
        $output = shell_exec($cmd);
        echo !empty($output) ? $output : "File kosong atau tidak ditemukan.";
    }
    exit;
}

// 4. FITUR CLEAR MEMORY
if (isset($_GET['clear'])) {
    $_SESSION['chat_history'] = [];
    echo "OK";
    exit;
}

// 5. FITUR STREAMING & PERSONA AGENT
if (isset($_GET['stream'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    if (!isset($_SESSION['chat_history'])) { $_SESSION['chat_history'] = []; }

    if (count($_SESSION['chat_history']) > 40) {
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -40);
    }

    $knowledge_path = '/var/www/streamify/app/backend-vps/api/streamify_knowledge.txt';
    $knowledge = file_exists($knowledge_path) ? file_get_contents($knowledge_path) : "Tidak ada file knowledge.";
    
    $system_prompt = "Kamu asisten koding senior (Gaya Claude). JAWAB SINGKAT, TO-THE-POINT.\n\n"
        . "=== FITUR AUTO-SEARCH (BACA SERVER OTOMATIS) ===\n"
        . "Kamu sekarang bisa mengecek isi VPS user secara mandiri.\n"
        . "- Jika butuh mencari kata kunci di project (misal nyari file reelife), balas HANYA dengan: [CARI: kata_kunci]\n"
        . "- Jika butuh membaca isi file spesifik, balas HANYA dengan: [BACA: /path/ke/file.php]\n"
        . "ATURAN ALAT: Jangan tulis apapun selain tag di atas. Tunggu sistem membalas dengan hasil filenya.\n\n"
        . "=== ATURAN MENJAWAB (MUTLAK) ===\n"
        . "1. JANGAN PERNAH mengulang atau menuliskan ulang isi kodingan/file yang baru saja kamu baca ke layar user. User tidak butuh melihat isinya!\n"
        . "2. Cukup baca di dalam 'pikiranmu', pahami logikanya, lalu LANGSUNG berikan 1 command eksekusi (`sed` atau `cat << 'EOF'`) untuk memperbaiki masalah yang ditanyakan.\n"
        . "3. DILARANG MEMBERI ALTERNATIF OPSI.\n\n"
        . "Konteks Proyek:\n" . $knowledge;
    
    $messages = array_merge([["role" => "system", "content" => $system_prompt]], $_SESSION['chat_history']);

    $ch = curl_init("https://api.deepseek.com/chat/completions");
    $data = json_encode([
        "model" => (isset($_GET['model']) && $_GET['model'] === 'reasoner') ? 'deepseek-reasoner' : 'deepseek-chat',
        "messages" => $messages,
        "stream" => true
    ]);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer $api_key"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    
    $full_response = "";
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $json_str = substr($line, 6);
                if (trim($json_str) == '[DONE]') continue;
                $json = json_decode($json_str, true);
                if (isset($json['choices'][0]['delta']['content'])) {
                    $full_response .= $json['choices'][0]['delta']['content'];
                }
            }
        }
        echo $data;
        ob_flush();
        flush();
        return strlen($data);
    });

    curl_exec($ch);
    curl_close($ch);
    
    if (!empty($full_response)) {
        $_SESSION['chat_history'][] = ["role" => "assistant", "content" => $full_response];
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>DeepSeek Admin Chat - PlayAll</title>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        body { background: #1a1a1a; color: #e0e0e0; font-family: sans-serif; display: flex; flex-direction: column; height: 100vh; margin: 0; }
        .header { background: #222; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #444; flex-wrap: wrap; gap: 10px; }
        .header-title { margin: 0; color: #fff; font-size: 16px; flex: 1; }
        .header-buttons { display: flex; gap: 10px; }
        .btn-logout { background: #dc3545; color: white; text-decoration: none; padding: 8px 12px; border-radius: 5px; font-size: 13px; font-weight: bold; }
        .btn-clear { background: #ff9800; color: white; border: none; padding: 8px 12px; border-radius: 5px; font-size: 13px; font-weight: bold; cursor: pointer; }
        
        #chat-box { flex: 1; overflow-y: auto; padding: 15px; }
        
        .msg { margin-bottom: 15px; padding: 15px; border-radius: 8px; max-width: 85%; line-height: 1.6; word-wrap: break-word; }
        .user { background: #2c3e50; align-self: flex-end; margin-left: auto; color: #fff; white-space: pre-wrap; }
        .bot { background: #2a2a2a; border: 1px solid #444; }
        .system { background: #5a4000; color: #ffd700; text-align: center; font-size: 0.9em; margin: 10px auto; padding: 5px 15px; border-radius: 5px; width: fit-content; }
        
        pre { background: #000; padding: 15px; border-radius: 5px; overflow-x: auto; position: relative; border: 1px solid #333; margin-top: 5px; }
        code { font-family: monospace; color: #0f0; }
        p code { background: #333; padding: 2px 6px; border-radius: 4px; color: #ff9800; }
        .copy-btn { position: absolute; top: 5px; right: 5px; background: #444; color: #fff; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px; font-size: 12px; }
        .copy-btn:hover { background: #666; }
        
        .input-area { background: #252525; padding: 10px 15px; display: flex; gap: 10px; border-top: 1px solid #444; align-items: flex-end; padding-bottom: 20px; }
        textarea { flex: 1; background: #333; border: 1px solid #555; color: #fff; padding: 12px; border-radius: 5px; outline: none; resize: none; min-height: 20px; max-height: 120px; font-family: inherit; font-size: 14px; }
        .btn-kirim { background: #007bff; color: white; border: none; padding: 0 20px; border-radius: 5px; cursor: pointer; font-weight: bold; height: 46px; }
        .btn-kirim:disabled { background: #555; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="header">
        <h3 class="header-title">🤖 DeepSeek Agent Panel</h3>
        <div class="header-buttons">
            <select id="modelSelect" style="background: #333; color: #fff; border: 1px solid #555; border-radius: 5px; padding: 5px; outline: none; cursor: pointer; font-size: 13px; font-weight: bold;">
                <option value="chat">⚡ V3 (Cepat)</option>
                <option value="reasoner">🧠 R1 (Mikir)</option>
            </select>
            <button class="btn-clear" onclick="clearChat()">🗑️ Clear Memory</button>
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div id="chat-box"></div>
    
    <div class="input-area">
        <textarea id="userInput" placeholder="Tanya koding di sini..." rows="1" oninput="autoResize(this)"></textarea>
        <button id="sendBtn" class="btn-kirim" onclick="sendMessage()">Kirim</button>
    </div>

    <script>
        const chatBox = document.getElementById('chat-box');
        const userInput = document.getElementById('userInput');
        const sendBtn = document.getElementById('sendBtn');

        function autoResize(el) { el.style.height = 'auto'; el.style.height = (el.scrollHeight) + 'px'; }

        function appendMsg(text, type, isMarkdown = false) {
            const div = document.createElement('div');
            div.className = `msg ${type}`;
            div.innerHTML = isMarkdown ? marked.parse(text) : text;
            chatBox.appendChild(div);
            chatBox.scrollTop = chatBox.scrollHeight;
            return div;
        }

        function addCopyButtons(container) {
            container.querySelectorAll('pre').forEach(pre => {
                if(pre.querySelector('.copy-btn')) return;
                const codeBlock = pre.querySelector('code');
                const btn = document.createElement('button');
                btn.className = 'copy-btn';
                btn.innerText = 'Copy';
                btn.onclick = () => {
                    navigator.clipboard.writeText(codeBlock.innerText);
                    btn.innerText = 'Copied!';
                    btn.style.background = '#28a745';
                    setTimeout(() => { btn.innerText = 'Copy'; btn.style.background = '#444'; }, 2000);
                };
                pre.appendChild(btn);
            });
        }

        function clearChat() {
            if(confirm('Yakin ingin mereset memori obrolan?')) {
                fetch('?clear=1').then(() => {
                    chatBox.innerHTML = '';
                    appendMsg('🧹 Memory Reset! Konteks dibersihkan.', 'system');
                });
            }
        }

        function sendMessage() {
            const text = userInput.value.trim();
            if (!text) return;
            appendMsg(text.replace(/\n/g, '<br>'), 'user');
            userInput.value = '';
            autoResize(userInput);
            startStream(text);
        }

        function checkToolExecution(botText) {
            let matchBaca = botText.match(/\[BACA:\s*(.+?)\]/i);
            let matchCari = botText.match(/\[CARI:\s*(.+?)\]/i);

            if (matchBaca || matchCari) {
                let tool = matchBaca ? 'baca' : 'cari';
                let arg = matchBaca ? matchBaca[1] : matchCari[1];

                appendMsg(`⚙️ AI mengeksekusi otomatis: ${tool.toUpperCase()} ${arg}...`, 'system');

                fetch(`?tool=${tool}&arg=${encodeURIComponent(arg)}`)
                .then(r => r.text())
                .then(res => {
                    let toolResult = `[HASIL SISTEM - ${tool.toUpperCase()}]\n${res}\n\n(File sudah dibaca. INGAT ATURAN MUTLAK: JANGAN PERNAH tulis ulang isi file ini ke user! Langsung berikan analisa/kesimpulan singkat dan 1 command sed/cat untuk solusi.)`;
                    // Kirim balik hasilnya secara rahasia ke AI supaya AI lanjut mikir, dan pastikan respon akhirnya terlihat
                    startStream(toolResult, false);
                });
                return true;
            }
            return false;
        }

        function startStream(messageText, isHidden = false) {
            const botMsgDiv = document.createElement('div');
            botMsgDiv.className = 'msg bot';
            if (isHidden) botMsgDiv.style.display = 'none';
            chatBox.appendChild(botMsgDiv);
            
            sendBtn.disabled = true;
            let rawContent = '';

            // Kirim pesan lewat metode POST supaya tidak ada limit panjang URL
            fetch('?', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'save_msg=' + encodeURIComponent(messageText)
            }).then(() => {
                let modelVal = document.getElementById("modelSelect") ? document.getElementById("modelSelect").value : "chat"; const source = new EventSource("?stream=1&model=" + modelVal);
                
                source.onmessage = (e) => {
                    if (e.data === '[DONE]') {
                        source.close();
                        sendBtn.disabled = false;
                        addCopyButtons(botMsgDiv);
                        
                        let isTool = checkToolExecution(rawContent);
                        if (isTool) {
                            // Kalau dia cuma manggil alat, sembunyikan kotak pesan [CARI]/[BACA]-nya dari mata user
                            botMsgDiv.style.display = 'none'; 
                        }
                        return;
                    }
                    try {
                        const json = JSON.parse(e.data);
                        const content = json.choices[0].delta.content;
                        if (content) {
                            rawContent += content;
                            botMsgDiv.innerHTML = marked.parse(rawContent);
                            chatBox.scrollTop = chatBox.scrollHeight;
                        }
                    } catch (err) {}
                };

                source.onerror = () => {
                    source.close();
                    sendBtn.disabled = false;
                    botMsgDiv.innerHTML += "<br><span style='color:red;'>[Koneksi terputus]</span>";
                };
            });
        }
    </script>
</body>
</html>
