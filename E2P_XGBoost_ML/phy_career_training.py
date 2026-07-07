# =====================================================
# PHASE 2: PHYSICS/ENGINEERING CLUSTER -> CAREER TRAINING
# =====================================================

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
CSV_FILE = "Entry2Pros.csv"
df = pd.read_csv(CSV_FILE)

PHY_LABEL = "Physics/Engineering"

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

# -------------------------
# STEP 2: Clean numeric columns (safe after manual edits)
# -------------------------
for col in feature_columns:
    df[col] = pd.to_numeric(df[col], errors="coerce")

df = df.dropna(subset=["Cluster", "Career"]).copy()
df[feature_columns] = df[feature_columns].fillna(df[feature_columns].median(numeric_only=True))

# -------------------------
# STEP 3: Filter Physics/Engineering cluster
# -------------------------
df_phy = df[df["Cluster"] == PHY_LABEL].copy()

if df_phy.empty:
    print(f"ERROR: No data found for {PHY_LABEL} cluster")
    print("Available clusters:")
    print(df["Cluster"].value_counts())
    raise ValueError(f"{PHY_LABEL} cluster not found")

print(f"Rows in {PHY_LABEL} cluster: {len(df_phy)}")

# -------------------------
# STEP 4: Features & target
# -------------------------
# Validate columns exist (prevents silent errors)
required_cols = ["Cluster", "Career"] + feature_columns
missing = [c for c in required_cols if c not in df_phy.columns]
if missing:
    raise ValueError(f"Missing columns in CSV ({PHY_LABEL} subset): {missing}")

X = df_phy[feature_columns]
y = df_phy["Career"].astype(str)

# -------------------------
# STEP 5: Remove small classes (stable)
# -------------------------
MIN_SAMPLES = 4
career_counts = y.value_counts()
keep_careers = career_counts[career_counts >= MIN_SAMPLES].index

df_phy = df_phy[df_phy["Career"].isin(keep_careers)].copy()
X = df_phy[feature_columns]
y = df_phy["Career"].astype(str)

print(f"\nCareer counts in {PHY_LABEL} (after filtering small classes):")
print(y.value_counts())

num_unique = y.nunique()
print(f"\nNumber of {PHY_LABEL} careers: {num_unique}")

if num_unique < 2:
    raise ValueError("Not enough career classes to train. Need at least 2.")

# -------------------------
# STEP 6: Encode labels
# -------------------------
career_encoder = LabelEncoder()
y_encoded = career_encoder.fit_transform(y)

num_classes = len(career_encoder.classes_)

print("\nCareer label mapping:")
for i, c in enumerate(career_encoder.classes_):
    print(f"{c} -> {i}")

# -------------------------
# STEP 7: Sample weights (balanced)
# -------------------------
class_counts = pd.Series(y_encoded).value_counts().sort_index()
class_weights = (class_counts.sum() / (len(class_counts) * class_counts)).to_dict()
sample_weight = np.array([class_weights[c] for c in y_encoded], dtype=float)

# -------------------------
# STEP 8: Train-test split (include weights)
# -------------------------
X_train, X_test, y_train, y_test, w_train, w_test = train_test_split(
    X,
    y_encoded,
    sample_weight,
    test_size=0.2,
    random_state=42,
    stratify=y_encoded
)

# -------------------------
# STEP 9: Train XGBoost
# NOTE: use softprob (better for probabilities in API)
# -------------------------
model = XGBClassifier(
    objective="multi:softprob",
    num_class=num_classes,

    n_estimators=600,
    max_depth=2,
    learning_rate=0.03,

    subsample=0.9,
    colsample_bytree=0.9,

    reg_lambda=2.0,
    reg_alpha=0.5,

    min_child_weight=3,
    gamma=0.2,

    eval_metric="mlogloss",
    random_state=42
)

model.fit(X_train, y_train, sample_weight=w_train)

# -------------------------
# STEP 10: Evaluate
# -------------------------
y_pred = model.predict(X_test)

accuracy = accuracy_score(y_test, y_pred)
print(f"\nPhase 2 (Physics/Engineering → Career) Accuracy: {accuracy*100:.2f}%")

print(f"\nClassification Report ({PHY_LABEL} careers):")
print(classification_report(
    y_test,
    y_pred,
    target_names=career_encoder.classes_,
    zero_division=0
))

# -------------------------
# STEP 11: Confusion Matrix (SAVE AS IMAGE - no popup)
# -------------------------
cm = confusion_matrix(y_test, y_pred)

os.makedirs("models", exist_ok=True)

plt.figure(figsize=(12, 10))
sns.heatmap(
    cm,
    annot=False,
    cmap="Oranges",
    xticklabels=career_encoder.classes_,
    yticklabels=career_encoder.classes_
)
plt.xlabel("Predicted Career")
plt.ylabel("Actual Career")
plt.title("Confusion Matrix - Physics/Engineering (Career)")
plt.xticks(rotation=90)
plt.tight_layout()

plt.savefig("models/confusion_matrix_phy.png", dpi=200, bbox_inches="tight")
plt.close()

print("Saved: models/confusion_matrix_phy.png")

# -------------------------
# STEP 12: Save model + meta for API inference
# -------------------------
model.get_booster().save_model("models/career_phy.json")

meta = {
    "cluster_label": PHY_LABEL,
    "feature_order": feature_columns,
    "class_names": career_encoder.classes_.tolist()
}

with open("models/meta_phy.json", "w", encoding="utf-8") as f:
    json.dump(meta, f, ensure_ascii=False, indent=2)

print("Saved: models/career_phy.json")
print("Saved: models/meta_phy.json")
