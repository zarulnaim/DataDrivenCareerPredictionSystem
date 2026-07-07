# ======================================
# PHASE 2: IT CLUSTER -> CAREER TRAINING
# ======================================

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
CSV_FILE = "Entry2Pros.csv"  # change if your file name differs
df = pd.read_csv(CSV_FILE)

# -------------------------
# STEP 2: Filter ONLY IT cluster
# -------------------------
IT_LABEL = "Information Technology"
df_it = df[df["Cluster"] == IT_LABEL].copy()

if df_it.empty:
    print("ERROR: No data found for IT cluster =", IT_LABEL)
    print("Available clusters:")
    print(df["Cluster"].value_counts())
    raise ValueError("IT cluster not found. Fix IT_LABEL to match your CSV.")

print(f"Rows in Information Technology cluster: {len(df_it)}")

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

# Validate columns exist
required_cols = ["Cluster", "Career"] + feature_columns
missing = [c for c in required_cols if c not in df_it.columns]
if missing:
    raise ValueError(f"Missing columns in CSV (IT subset): {missing}")

X = df_it[feature_columns]
y = df_it["Career"].astype(str)

# -------------------------
# STEP 4: Remove very small classes (safety)
# -------------------------
MIN_SAMPLES = 2
career_counts = y.value_counts()
keep_careers = career_counts[career_counts >= MIN_SAMPLES].index

df_it = df_it[df_it["Career"].isin(keep_careers)].copy()
X = df_it[feature_columns]
y = df_it["Career"].astype(str)

print("\nCareer counts in Information Technology (after filtering small classes):")
print(y.value_counts())

# -------------------------
# STEP 5: Encode labels
# -------------------------
career_encoder = LabelEncoder()
y_encoded = career_encoder.fit_transform(y)

num_classes = len(career_encoder.classes_)
print(f"\nNumber of Information Technology careers: {num_classes}")

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
    n_estimators=300,
    max_depth=4,
    learning_rate=0.05,
    subsample=0.8,
    colsample_bytree=0.8,
    eval_metric="mlogloss",
    random_state=42
)

model.fit(X_train, y_train)

# -------------------------
# STEP 8: Evaluation
# -------------------------
y_pred = model.predict(X_test)

acc = accuracy_score(y_test, y_pred)
print(f"\nPhase 2 (Information Technology → Career) Accuracy: {acc*100:.2f}%")

print("\nClassification Report (Information Technology careers):")
print(classification_report(y_test, y_pred, target_names=career_encoder.classes_))

# -------------------------
# STEP 9: Confusion Matrix (SAVE AS IMAGE - no popup)
# -------------------------
cm = confusion_matrix(y_test, y_pred)

os.makedirs("models", exist_ok=True)

plt.figure(figsize=(12, 10))
sns.heatmap(
    cm,
    annot=False,
    cmap="Blues",
    xticklabels=career_encoder.classes_,
    yticklabels=career_encoder.classes_
)
plt.xlabel("Predicted Career")
plt.ylabel("Actual Career")
plt.title("Confusion Matrix - Information Technology (Career)")
plt.xticks(rotation=90)
plt.tight_layout()

plt.savefig("models/confusion_matrix_it.png", dpi=200, bbox_inches="tight")
plt.close()

print("Saved: models/confusion_matrix_it.png")

# -------------------------
# STEP 10: Save model + meta for API inference
# -------------------------
model.get_booster().save_model("models/career_it.json")

meta = {
    "cluster_label": IT_LABEL,
    "feature_order": feature_columns,
    "class_names": career_encoder.classes_.tolist()
}

with open("models/meta_it.json", "w", encoding="utf-8") as f:
    json.dump(meta, f, ensure_ascii=False, indent=2)

print("Saved: models/career_it.json")
print("Saved: models/meta_it.json")
