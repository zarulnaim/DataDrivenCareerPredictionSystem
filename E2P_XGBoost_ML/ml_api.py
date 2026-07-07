from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import json
import numpy as np
import xgboost as xgb
from pathlib import Path

app = FastAPI()

BASE = Path(__file__).resolve().parent
MODELS = BASE / "models"

# Map DB cluster label -> model/meta filenames
CLUSTER_FILES = {
    "Information Technology": ("career_it.json", "meta_it.json"),
    "Accounting/Finance": ("career_acc.json", "meta_acc.json"),
    "Biology": ("career_bio.json", "meta_bio.json"),
    "Physics/Engineering": ("career_phy.json", "meta_phy.json"),
}

# Load all models/meta at startup (so no “IT result keluar bila pilih Physics”)
loaded = {}
load_errors = {}

for cluster_label, (model_file, meta_file) in CLUSTER_FILES.items():
    model_path = MODELS / model_file
    meta_path = MODELS / meta_file

    if not model_path.exists() or not meta_path.exists():
        load_errors[cluster_label] = f"Missing {model_file} or {meta_file}"
        continue

    meta = json.loads(meta_path.read_text(encoding="utf-8"))
    booster = xgb.Booster()
    booster.load_model(str(model_path))
    loaded[cluster_label] = {"meta": meta, "booster": booster}

class PredictIn(BaseModel):
    field_cluster: str  # must match DB label (Information Technology, etc.)
    O_score: float
    C_score: float
    E_score: float
    A_score: float
    N_score: float
    numerical: float
    spatial: float
    perceptual: float
    abstract_reasoning: float
    verbal: float
    cgpa: float

@app.get("/health")
def health():
    return {
        "ok": True,
        "loaded_models": list(loaded.keys()),
        "load_errors": load_errors
    }

@app.post("/predict")
def predict(inp: PredictIn):
    if inp.field_cluster not in CLUSTER_FILES:
        raise HTTPException(
            status_code=400,
            detail=f"Unknown field_cluster: {inp.field_cluster}. Allowed: {list(CLUSTER_FILES.keys())}"
        )

    if inp.field_cluster not in loaded:
        raise HTTPException(
            status_code=500,
            detail=f"Model/meta not loaded for {inp.field_cluster}. {load_errors.get(inp.field_cluster, '')}"
        )

    meta = loaded[inp.field_cluster]["meta"]
    booster = loaded[inp.field_cluster]["booster"]

    feature_order = meta["feature_order"]   # CSV feature names (with spaces)
    class_names = meta["class_names"]       # index -> career string

    incoming_map = {
        "O_score": inp.O_score,
        "C_score": inp.C_score,
        "E_score": inp.E_score,
        "A_score": inp.A_score,
        "N_score": inp.N_score,
        "Numerical Aptitude": inp.numerical,
        "Spatial Aptitude": inp.spatial,
        "Perceptual Aptitude": inp.perceptual,
        "Abstract Reasoning": inp.abstract_reasoning,
        "Verbal Reasoning": inp.verbal,
        "GPA": inp.cgpa,
    }

    try:
        x = np.array([[incoming_map[f] for f in feature_order]], dtype=float)
    except KeyError as e:
        raise HTTPException(status_code=500, detail=f"Missing feature mapping for: {str(e)}")

    dmat = xgb.DMatrix(x, feature_names=feature_order)

    # multiclass probabilities
    probs = booster.predict(dmat)[0]
    if len(probs) != len(class_names):
        raise HTTPException(status_code=500, detail="Prediction size mismatch with class_names.")

    best_idx = int(np.argmax(probs))
    top_idx = np.argsort(probs)[::-1][:3]
    top3 = [{"career": class_names[i], "prob": float(probs[i])} for i in top_idx]

    # ----------------------------
    # Explain: contribution shares
    # ----------------------------
    # pred_contribs includes bias as last column
    contrib_raw = booster.predict(dmat, pred_contribs=True)
    arr = np.array(contrib_raw)

    n_features = len(feature_order)
    n_classes = len(class_names)

    # Normalize shape for multiclass contributions
    # Possible shapes:
    # (1, n_classes*(n_features+1)) OR (1, n_classes, n_features+1) OR (n_classes, n_features+1)
    if arr.ndim == 2 and arr.shape[0] == 1 and arr.shape[1] == n_classes * (n_features + 1):
        arr = arr.reshape(n_classes, n_features + 1)
    elif arr.ndim == 3 and arr.shape[0] == 1:
        arr = arr[0]
    elif arr.ndim == 2 and arr.shape[0] == n_classes:
        pass
    else:
        arr = None

    top_factors = []
    if arr is not None:
        row = arr[best_idx]  # contributions for predicted class
        feat_vals = row[:n_features]  # ignore bias at last index

        feat_contrib = list(zip(feature_order, feat_vals.tolist()))

        # Only positive “push” factors
        positives = [(f, v) for f, v in feat_contrib if v > 0]
        positives_sorted = sorted(positives, key=lambda t: t[1], reverse=True)

        top3_pos = positives_sorted[:3]
        sum_pos = sum(v for _, v in top3_pos) if top3_pos else 0.0

        # share_percent: among top3 positive contributors (cleaner, stable)
        for f, v in top3_pos:
            share = (v / sum_pos * 100.0) if sum_pos > 0 else 0.0
            top_factors.append({
                "feature": f,
                "impact": float(v),                 # raw contribution value (relative)
                "share_percent": float(share)       # % share among top3 positives
            })

    return {
        "field_cluster_used": inp.field_cluster,
        "top3": top3,
        "explain": {
            "predicted_index": best_idx,
            "top_factors": top_factors
        }
    }
