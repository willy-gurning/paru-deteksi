let mediaRecorder;
let audioChunks = [];

const recordButton = document.getElementById("record");
const stopButton = document.getElementById("stop");

recordButton.onclick = async () => {
  const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
  mediaRecorder = new MediaRecorder(stream);
  mediaRecorder.start();

  mediaRecorder.ondataavailable = event => {
    audioChunks.push(event.data);
  };

  mediaRecorder.onstop = () => {
    const audioBlob = new Blob(audioChunks, { type: "audio/wav" });
    const formData = new FormData();
    formData.append("file", audioBlob, "suara.wav"); // nama HARUS "file" biar Flask paham

    fetch("/predict", {
      method: "POST",
      body: formData,
    }).then(response => response.json())
      .then(data => {
        console.log("Hasil prediksi:", data);
        alert("Hasil: " + data.result);
      });
  };
};

stopButton.onclick = () => {
  mediaRecorder.stop();
};
