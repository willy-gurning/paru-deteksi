import pandas as pd
import numpy as np
from sklearn.preprocessing import StandardScaler
from sklearn.neighbors import KNeighborsClassifier
import pickle
import os

# Path dataset
dataset_path = os.path.join(os.path.dirname(__file__), 'dataset_mfcc.csv')

# Baca dataset
df = pd.read_csv(dataset_path)

# Pisahkan fitur dan label
X = df.drop('label', axis=1)
y = df['label']

# Normalisasi fitur
scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)

# Buat dan latih model KNN
knn = KNeighborsClassifier(n_neighbors=5)
knn.fit(X_scaled, y)

# Simpan model dan scaler
model_path = os.path.join(os.path.dirname(__file__), 'model.pkl')
scaler_path = os.path.join(os.path.dirname(__file__), 'scaler.pkl')

with open(model_path, 'wb') as f:
    pickle.dump(knn, f)

with open(scaler_path, 'wb') as f:
    pickle.dump(scaler, f)

print("Model dan Scaler berhasil disimpan.")
