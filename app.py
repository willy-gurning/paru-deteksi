from flask import Flask, request, render_template
from werkzeug.utils import secure_filename
import os
import librosa
import numpy as np
import pickle
import traceback
import json
from time import perf_counter

# === Setup Flask ===
app = Flask(__name__)
UPLOAD_FOLDER = os.path.join(os.path.dirname(__file__), 'uploads')
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# === Logging ke file ===
log_path = os.path.join(os.path.dirname(__file__), 'log.txt')

# === Load Model dan Scaler ===
model_path = os.path.join(os.path.dirname(__file__), 'model.pkl')
scaler_path = os.path.join(os.path.dirname(__file__), 'scaler.pkl')

with open(model_path, 'rb') as f:
    model = pickle.load(f)

with open(scaler_path, 'rb') as f:
    scaler = pickle.load(f)

# === Fungsi simpan hasil ke result.json ===
def simpan_hasil_ke_json(hasil):
    result_path = os.path.join(os.path.dirname(__file__), 'result.json')
    with open(result_path, 'w') as f:
        json.dump(hasil, f)

# === Halaman utama dengan form upload ===
@app.route('/')
def index():
    return render_template('upload.html')

# === Endpoint prediksi ===
@app.route('/predict', methods=['POST'])
def predict():
    filepath = None
    try:
        with open(log_path, 'a') as log_file:
            log_file.write('--- MASUK ENDPOINT /predict ---\n')

        if 'file' not in request.files:
            return 'File tidak ditemukan.', 400

        file = request.files['file']
        if not file or not file.filename:
            return 'File tidak valid.', 400

        file_content = file.read()
        if not file_content or len(file_content) < 1000:
            return 'File kosong atau terlalu kecil, silakan upload ulang.', 400

        file.stream.seek(0)
        filename = secure_filename(file.filename)
        filepath = os.path.join(UPLOAD_FOLDER, filename)
        file.save(filepath)

        with open(log_path, 'a') as log_file:
            log_file.write(f'File disimpan di: {filepath}\n')
            log_file.write(f'Size file: {len(file_content)} bytes\n')

        # === Hitung waktu proses mulai ===
        t_start = perf_counter()

        # === Proses audio ===
        try:
            y, sr = librosa.load(filepath, sr=22050, duration=3.0)
        except Exception as e:
            with open(log_path, 'a') as log_file:
                log_file.write(f'Error loading audio: {str(e)}\n')
            return "File audio tidak valid atau rusak.", 400

        # Ekstraksi MFCC
        mfcc = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=13)
        mfcc_mean = np.mean(mfcc.T, axis=0).reshape(1, -1)

        with open(log_path, 'a') as log_file:
            log_file.write(f'MFCC shape: {mfcc_mean.shape}\n')

        # Prediksi
        mfcc_scaled = scaler.transform(mfcc_mean)
        prediction = model.predict(mfcc_scaled)[0]

        # === Hitung waktu proses selesai ===
        t_end = perf_counter()
        elapsed_ms = (t_end - t_start) * 1000  # Konversi ke milidetik

        # Simpan ke JSON hasil + waktu
        simpan_hasil_ke_json({'hasil': prediction, 'waktu_ms': round(elapsed_ms, 2)})

        color = "green" if prediction.lower() == "normal" else "red"

        return f"""
        <html>
        <head>
          <title>Hasil Deteksi</title>
          <style>
            body {{
              font-family: 'Segoe UI', sans-serif;
              text-align: center;
              padding-top: 100px;
              background-color: #f9f9f9;
            }}
            .hasil {{
              font-size: 36px;
              font-weight: bold;
              color: {color};
              margin-bottom: 30px;
            }}
            .waktu {{
              font-size: 20px;
              color: #333;
              margin-bottom: 20px;
            }}
            .btn {{
              display: inline-block;
              padding: 12px 24px;
              font-size: 16px;
              font-weight: bold;
              color: white;
              background-color: #007BFF;
              border: none;
              border-radius: 8px;
              text-decoration: none;
            }}
            .btn:hover {{
              background-color: #0056b3;
            }}
          </style>
        </head>
        <body>
          <div class="hasil">Hasil Deteksi: {prediction}</div>
          <div class="waktu">Waktu proses: {elapsed_ms:.2f} ms</div>
          <a href="/" class="btn">🔁 Deteksi Lagi</a>
        </body>
        </html>
        """

    except Exception as e:
        with open(log_path, 'a') as log_file:
            log_file.write('--- ERROR ---\n')
            log_file.write(traceback.format_exc())
            log_file.write('\n')
        return "<h4 style='color:red'>Terjadi error saat prediksi. Cek file log.txt</h4>", 500

    finally:
        if filepath and os.path.exists(filepath):
            os.remove(filepath)
            with open(log_path, 'a') as log_file:
                log_file.write(f'File {filepath} dihapus setelah diproses\n')

# === Endpoint ambil hasil untuk ESP32 ===
@app.route('/result.json')
def ambil_hasil():
    result_path = os.path.join(os.path.dirname(__file__), 'result.json')
    if os.path.exists(result_path):
        with open(result_path, 'r') as f:
            return f.read(), 200, {'Content-Type': 'application/json'}
    else:
        return json.dumps({'hasil': 'Belum ada hasil'}), 200, {'Content-Type': 'application/json'}

# === Entry Point untuk WSGI dan Lokal ===
application = app

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 8080))
    app.run(host='0.0.0.0', port=port)

