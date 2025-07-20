<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <form id="uploadForm" action="../predict" method="POST" enctype="multipart/form-data">
      <input type="file" name="file" accept=".wav" required>
      <input type="submit" value="Deteksi dari File">
    </form>

    <hr>

    <!-- Rekam Suara -->
    <button id="startBtn">🎙️ Mulai Rekam</button>
    <button id="stopBtn" disabled>⏹️ Stop Rekaman</button>
    <form id="recordForm" action="../predict" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="fromRecord" value="true">
      <audio id="audioPreview" controls></audio>
      <input type="submit" value="Deteksi dari Rekaman" disabled id="submitRecord">
    </form>

    <div class="note">Gunakan Chrome/Firefox. Rekaman disimpan sebagai file .wav valid.</div>
  </div>

  <script>
    let mediaRecorder;
    let audioChunks = [];

    const startBtn = document.getElementById("startBtn");
    const stopBtn = document.getElementById("stopBtn");
    const audioPreview = document.getElementById("audioPreview");
    const recordForm = document.getElementById("recordForm");
    const submitRecord = document.getElementById("submitRecord");
    const loadingDiv = document.getElementById("loading");

    startBtn.onclick = async () => {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const context = new AudioContext();
      const source = context.createMediaStreamSource(stream);
      const processor = context.createScriptProcessor(4096, 1, 1);
      const buffer = [];

      processor.onaudioprocess = e => {
        buffer.push(new Float32Array(e.inputBuffer.getChannelData(0)));
      };

      source.connect(processor);
      processor.connect(context.destination);

      startBtn.disabled = true;
      stopBtn.disabled = false;

      stopBtn.onclick = () => {
        processor.disconnect();
        source.disconnect();
        context.close();

        const flatBuffer = flattenArray(buffer);
        const wavBlob = encodeWAV(flatBuffer, context.sampleRate);
        const wavUrl = URL.createObjectURL(wavBlob);
        audioPreview.src = wavUrl;

        const file = new File([wavBlob], "rekaman.wav", { type: "audio/wav" });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'file';
        fileInput.files = dataTransfer.files;
        recordForm.appendChild(fileInput);
        submitRecord.disabled = false;

        startBtn.disabled = false;
        stopBtn.disabled = true;
      };
    };

    function flattenArray(channelBuffer) {
      const length = channelBuffer.reduce((acc, cur) => acc + cur.length, 0);
      const result = new Float32Array(length);
      let offset = 0;
      for (let i = 0; i < channelBuffer.length; i++) {
        result.set(channelBuffer[i], offset);
        offset += channelBuffer[i].length;
      }
      return result;
    }

    function encodeWAV(samples, sampleRate) {
      const buffer = new ArrayBuffer(44 + samples.length * 2);
      const view = new DataView(buffer);

      function writeString(view, offset, string) {
        for (let i = 0; i < string.length; i++) {
          view.setUint8(offset + i, string.charCodeAt(i));
        }
      }

      const volume = 1;
      let offset = 0;

      writeString(view, offset, 'RIFF'); offset += 4;
      view.setUint32(offset, 36 + samples.length * 2, true); offset += 4;
      writeString(view, offset, 'WAVE'); offset += 4;
      writeString(view, offset, 'fmt '); offset += 4;
      view.setUint32(offset, 16, true); offset += 4;
      view.setUint16(offset, 1, true); offset += 2;
      view.setUint16(offset, 1, true); offset += 2;
      view.setUint32(offset, sampleRate, true); offset += 4;
      view.setUint32(offset, sampleRate * 2, true); offset += 4;
      view.setUint16(offset, 2, true); offset += 2;
      view.setUint16(offset, 16, true); offset += 2;
      writeString(view, offset, 'data'); offset += 4;
      view.setUint32(offset, samples.length * 2, true); offset += 4;

      for (let i = 0; i < samples.length; i++, offset += 2) {
        const s = Math.max(-1, Math.min(1, samples[i]));
        view.setInt16(offset, s * 0x7FFF * volume, true);
      }

      return new Blob([view], { type: 'audio/wav' });
    }

    // Loading Animation
    document.getElementById("uploadForm").addEventListener("submit", () => {
      loadingDiv.style.display = "block";
    });

    document.getElementById("recordForm").addEventListener("submit", () => {
      loadingDiv.style.display = "block";
    });
  </script>
</body>
</html>
