# ==========================================
# PHASE 2: BIOLOGY CLUSTER -> CAREER TRAINING
# ==========================================

import pandas as pd
import numpy as np
import os
import json

from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix

from xgboost import XGBClassifier
import seaborn as sns
import matplotlib.pyplot as plt

np.random.seed(42)

# -------------------------
# STEP 1: Load dataset
# -------------------------
CSV_FILE = "Entry2Pros.csv"  # tukar kalau nama lain
df = pd.read_csv(CSV_FILE)

# -------------------------
# STEP 2: Filter BIOLOGY cluster
# -------------------------
BIO_LABEL = "Biology"
df_bio = df[df["Cluster"] == BIO_LABEL].copy()

if df_bio.empty:
    print("ERROR: No data found for Biology cluster =", BIO_LABEL)
    print("Available clusters:")
    print(df["Cluster"].value_counts())
    raise ValueError("Biology cluster not found. Fix BIO_LABEL to match your CSV.")

print(f"Rows in Biology cluster: {len(df_bio)}")

# -------------------------
# STEP 3: Features & target
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

# Validate columns exist (prevents silent errors)
required_cols = ["Cluster", "Career"] + feature_columns
missing = [c for c in required_cols if c not in df_bio.columns]
if missing:
    raise ValueError(f"Missing columns in CSV (Biology subset): {missing}")

X = df_bio[feature_columns]
y = df_bio["Career"].astype(str)

# -------------------------
# STEP 4: Remove very small classes (safety)
# -------------------------
MIN_SAMPLES = 2
career_counts = y.value_counts()
keep_careers = career_counts[career_counts >= MIN_SAMPLES].index

df_bio = df_bio[df_bio["Career"].isin(keep_careers)].copy()
X = df_bio[feature_columns]
y = df_bio["Career"].astype(str)

print("\nCareer counts in Biology (after filtering small classes):")
print(y.value_counts())

# -------------------------
# STEP 5: Encode labels
# -------------------------
career_encoder = LabelEncoder()
y_encoded = career_encoder.fit_transform(y)

num_classes = len(career_encoder.classes_)
print(f"\nNumber of Biology careers: {num_classes}")

if num_classes < 2:
    raise ValueError("Not enough career classes to train. Need at least 2.")

print("\nCareer label mapping:")
for i, c in enumerate(career_encoder.classes_):
    print(f"{c} -> {i}")

# -------------------------
# STEP 6: Train-test split
# -------------------------
X_train, X_test, y_train, y_test = train_test_split(
    X,
    y_encoded,
    test_size=0.2,
    random_state=42,
    stratify=y_encoded
)

# -------------------------
# STEP 7: Train XGBoost
# -------------------------
model = XGBClassifier(
    objective="multi:softprob",
    num_class=num_classes,
    n_estimators=200,
    max_depth=3,
    learning_rate=0.1,
    subsample=0.9,
    colsample_bytree=0.9,
    eval_metric="mlogloss",
    random_state=42
)

model.fit(X_train, y_train)

# -------------------------
# STEP 8: Evaluation
# -------------------------
y_pred = model.predict(X_test)

accuracy = accuracy_score(y_test, y_pred)
print(f"\nPhase 2 (Biology → Career) Accuracy: {accuracy*100:.2f}%")

print("\nClassification Report (Biology careers):")
print(classification_report(
    y_test,
    y_pred,
    target_names=career_encoder.classes_,
    zero_division=0
))

# -------------------------
# STEP 9: Confusion Matrix (SAVE AS IMAGE - no popup)
# -------------------------
cm = confusion_matrix(y_test, y_pred)

os.makedirs("models", exist_ok=True)

plt.figure(figsize=(12, 10))
sns.heatmap(
    cm,
    annot=False,
    cmap="Greens",
    xticklabels=career_encoder.classes_,
    yticklabels=career_encoder.classes_
)
plt.xlabel("Predicted Career")
plt.ylabel("Actual Career")
plt.title("Confusion Matrix - Biology (Career)")
plt.xticks(rotation=90)
plt.tight_layout()

plt.savefig("models/confusion_matrix_bio.png", dpi=200, bbox_inches="tight")
plt.close()

print("Saved: models/confusion_matrix_bio.png")

# -------------------------
# STEP 10: Save model + meta for API inference
# -------------------------
model.get_booster().save_model("models/career_bio.json")

meta = {
    "cluster_label": BIO_LABEL,
    "feature_order": feature_columns,
    "class_names": career_encoder.classes_.tolist()
}

with open("models/meta_bio.json", "w", encoding="utf-8") as f:
    json.dump(meta, f, ensure_ascii=False, indent=2)

print("Saved: models/career_bio.json")
print("Saved: models/meta_bio.json")
