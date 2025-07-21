<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Deteksi Penyakit Paru</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #4facfe, #00f2fe);
      margin: 0;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      color: #333;
    }
    .container {
      background: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      max-width: 480px;
      width: 100%;
      text-align: center;
      position: relative;
    }
    h2 {
      margin-bottom: 20px;
      color: #222;
    }
    input[type="file"],
    button {
      margin: 12px 0;
      border: 1px solid #ccc;
      padding: 10px;
      border-radius: 6px;
      width: 100%;
    }
    input[type="submit"] {
      background-color: #4facfe;
      color: white;
      border: none;
      padding: 12px 25px;
      font-size: 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.3s ease;
      width: 100%;
    }
    input[type="submit"]:hover {
      background-color: #00c6ff;
    }
    audio {
      margin-top: 10px;
      width: 100%;
    }
    .note {
      margin-top: 10px;
      font-size: 12px;
      color: #555;
    }
    #loading {
      display: none;
      position: absolute;
      top: 10px;
      left: 10px;
      right: 10px;
      padding: 10px;
      background: #fff3cd;
      border: 1px solid #ffeeba;
      border-radius: 8px;
      font-size: 14px;
      color: #856404;
      z-index: 10;
    }
  </style>
</head>
<body>
  <div class="container">
    <div id="loading">🔍 Menganalisis batuk... Mohon tunggu...</div>
    <h2>Deteksi Penyakit Paru dari Suara Batuk</h2>

    <!-- Upload Manual -->
    <form id="uploadForm" action="/predict" method="POST" enctype="multipart/form-data">
      <input type="file" name="file" accept=".wav" required />
      <input type="submit" value="Deteksi dari File" />
    </form>

    <hr />

    <!-- Rekam Suara -->
    <button id="startBtn">🎙️ Mulai Rekam</button>
    <button id="stopBtn" disabled>⏹️ Stop Rekaman</button>
    <form id="recordForm" method="POST" enctype="multipart/form-data">
      <audio id="audioPreview" controls></audio>
      <input type="submit" value="Deteksi dari Rekaman" disabled id="submitRecord" />
    </form>

    <div class="note">Gunakan Chrome/Firefox. Rekaman akan dikirim sebagai file .wav valid.</div>
  </div>

  <script>
    let mediaRecorder;
    let recordedChunks = [];

    const startBtn = document.getElementById("startBtn");
    const stopBtn = document.getElementById("stopBtn");
    const audioPreview = document.getElementById("audioPreview");
    const recordForm = document.getElementById("recordForm");
    const submitRecord = document.getElementById("submitRecord");
    const loadingDiv = document.getElementById("loading");

    startBtn.onclick = async () => {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      recordedChunks = [];
      mediaRecorder = new MediaRecorder(stream);

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) recordedChunks.push(event.data);
      };

      mediaRecorder.onstop = () => {
        const blob = new Blob(recordedChunks, { type: 'audio/wav' });
        const wavUrl = URL.createObjectURL(blob);
        audioPreview.src = wavUrl;

        const file = new File([blob], 'rekaman.wav', { type: 'audio/wav' });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);

        // Hapus input lama kalau ada
        const oldInput = recordForm.querySelector('input[type="file"]');
        if (oldInput) oldInput.remove();

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'file';
        fileInput.files = dataTransfer.files;
        recordForm.appendChild(fileInput);

        submitRecord.disabled = false;
      };

      mediaRecorder.start();
      startBtn.disabled = true;
      stopBtn.disabled = false;
    };

    stopBtn.onclick = () => {
      mediaRecorder.stop();
      startBtn.disabled = false;
      stopBtn.disabled = true;
    };

    // Manual upload form
    document.getElementById("uploadForm").addEventListener("submit", () => {
      loadingDiv.style.display = "block";
    });

    // Rekaman submit manual diganti fetch
    recordForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const fileInput = recordForm.querySelector('input[type="file"]');
      const file = fileInput?.files[0];

      if (!file) {
        alert("❌ Tidak ada file rekaman ditemukan.");
        return;
      }

      const formData = new FormData();
      formData.append("file", file);
      loadingDiv.style.display = "block";

      fetch("/predict", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.text())
        .then((html) => {
          loadingDiv.style.display = "none";
          document.body.innerHTML = html;
        })
        .catch((err) => {
          loadingDiv.style.display = "none";
          alert("❌ Gagal mengirim rekaman:\n" + err);
        });
    });
  </script>
</body>
</html>
