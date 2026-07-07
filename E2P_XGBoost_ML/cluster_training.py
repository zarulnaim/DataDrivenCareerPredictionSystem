# =========================
# PHASE 1: CLUSTER TRAINING (
# =========================
import os
import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
from xgboost import XGBClassifier
import seaborn as sns
import matplotlib.pyplot as plt
import joblib   

# -------------------------
# STEP 0: Reproducibility
# -------------------------
np.random.seed(42)

# -------------------------
# STEP 1: Load dataset
# -------------------------
CSV_FILE = "Entry2Pros.csv"
df = pd.read_csv(CSV_FILE)

# -------------------------
# STEP 2: Define features & target
# -------------------------
feature_columns = [
    "O_score",
    "C_score",
    "E_score",
    "A_score",
    "N_score",
    "Numerical Aptitude",
    "Spatial Aptitude",
    "Perceptual Aptitude",
    "Abstract Reasoning",
    "Verbal Reasoning",
    "GPA"
]
target_column = "Cluster"

# Validate columns
missing_features = [c for c in feature_columns if c not in df.columns]
if missing_features:
    raise ValueError(f"Missing feature columns in CSV: {missing_features}")

if target_column not in df.columns:
    raise ValueError(f"Missing target column '{target_column}' in CSV")

# -------------------------
# STEP 3: Clean data (safe for human-edited CSV)
# -------------------------
# Convert features to numeric (if any string accidentally exists)
for col in feature_columns:
    df[col] = pd.to_numeric(df[col], errors="coerce")

# Drop rows where Cluster is missing
df = df.dropna(subset=[target_column]).copy()

# Fill missing feature values with median (stable default)
df[feature_columns] = df[feature_columns].fillna(df[feature_columns].median(numeric_only=True))

# -------------------------
# STEP 4: Prepare X and y
# -------------------------
X = df[feature_columns]
y = df[target_column].astype(str)

print("Cluster counts:")
print(y.value_counts(), "\n")

# Encode cluster labels
label_encoder = LabelEncoder()
y_encoded = label_encoder.fit_transform(y)

print("Cluster label mapping:")
for i, label in enumerate(label_encoder.classes_):
    print(f"{label} -> {i}")

num_classes = len(label_encoder.classes_)

# -------------------------
# STEP 5: Train-test split
# -------------------------
X_train, X_test, y_train, y_test = train_test_split(
    X,
    y_encoded,
    test_size=0.2,
    random_state=42,
    stratify=y_encoded
)

# -------------------------
# STEP 6: Train XGBoost model (stable baseline)
# -------------------------
model = XGBClassifier(
    objective="multi:softprob",
    num_class=num_classes,      # AUTO (no hardcode 4)
    n_estimators=300,
    max_depth=5,
    learning_rate=0.05,
    subsample=0.8,
    colsample_bytree=0.8,
    eval_metric="mlogloss",
    random_state=42
)

model.fit(X_train, y_train)

# -------------------------
# STEP 7: Evaluate model
# -------------------------
y_pred = model.predict(X_test)

accuracy = accuracy_score(y_test, y_pred)
print("\nPhase 1 Accuracy:", f"{accuracy*100:.2f}%")

print("\nClassification Report:")
print(classification_report(y_test, y_pred, target_names=label_encoder.classes_))

# Confusion Matrix
cm = confusion_matrix(y_test, y_pred)

# Create the models directory if it doesn't exist
os.makedirs("models", exist_ok=True)

# Save the confusion matrix as an image
plt.figure(figsize=(8, 6))
sns.heatmap(
    cm,
    annot=True,
    fmt="d",
    cmap="Blues",
    xticklabels=label_encoder.classes_,
    yticklabels=label_encoder.classes_
)
plt.xlabel("Predicted Cluster")
plt.ylabel("Actual Cluster")
plt.title("Cluster Confusion Matrix")

# Save the confusion matrix as a PNG image file
plt.tight_layout()
plt.savefig("models/confusion_matrix_cluster.png", dpi=200, bbox_inches="tight")
plt.close()  # Close the plot to avoid display during script execution

print("Saved: models/confusion_matrix_cluster.png")
