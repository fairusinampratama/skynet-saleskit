import os
import tempfile
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Any

from fastapi import FastAPI, File, HTTPException, UploadFile
from PIL import Image, ImageOps


ocr_engine: Any = None


def configure_model_home() -> None:
    model_dir = os.environ.get("PADDLEOCR_MODEL_DIR")

    if not model_dir:
        return

    Path(model_dir).mkdir(parents=True, exist_ok=True)
    os.environ.setdefault("PADDLE_HOME", model_dir)
    os.environ.setdefault("PADDLEX_HOME", model_dir)
    os.environ.setdefault("PADDLE_PDX_CACHE_HOME", model_dir)


def create_ocr_engine() -> Any:
    from paddleocr import PaddleOCR

    kwargs = {
        "lang": os.environ.get("PADDLEOCR_LANG", "en"),
        "text_detection_model_name": os.environ.get("PADDLEOCR_DETECTION_MODEL", "PP-OCRv5_mobile_det"),
        "text_recognition_model_name": os.environ.get("PADDLEOCR_RECOGNITION_MODEL", "en_PP-OCRv5_mobile_rec"),
        "use_doc_orientation_classify": False,
        "use_doc_unwarping": False,
        "use_textline_orientation": False,
    }
    optional_kwargs = {
        "text_det_limit_side_len": env_int("PADDLEOCR_DET_LIMIT_SIDE_LEN"),
        "text_det_limit_type": os.environ.get("PADDLEOCR_DET_LIMIT_TYPE") or None,
        "text_det_thresh": env_float("PADDLEOCR_DET_THRESH"),
        "text_det_box_thresh": env_float("PADDLEOCR_DET_BOX_THRESH"),
        "text_det_unclip_ratio": env_float("PADDLEOCR_DET_UNCLIP_RATIO"),
        "text_rec_score_thresh": env_float("PADDLEOCR_REC_SCORE_THRESH"),
        "text_recognition_batch_size": env_int("PADDLEOCR_REC_BATCH_SIZE"),
    }
    kwargs.update({key: value for key, value in optional_kwargs.items() if value is not None})

    try:
        return PaddleOCR(**kwargs)
    except TypeError:
        return PaddleOCR(
            lang=kwargs["lang"],
            use_angle_cls=False,
            show_log=False,
        )


@asynccontextmanager
async def lifespan(app: FastAPI):
    global ocr_engine
    configure_model_home()
    ocr_engine = create_ocr_engine()
    yield


app = FastAPI(lifespan=lifespan)


@app.get("/health")
def health() -> dict[str, bool]:
    return {"ready": ocr_engine is not None}


@app.post("/ktp/read")
async def read_ktp(image: UploadFile = File(...)) -> dict[str, Any]:
    if ocr_engine is None:
        raise HTTPException(status_code=503, detail="PaddleOCR is not ready.")

    suffix = Path(image.filename or "ktp.jpg").suffix or ".jpg"

    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as temporary:
        temporary.write(await image.read())
        image_path = temporary.name

    try:
        optimize_image(image_path)
        rows = run_ocr(image_path)
    finally:
        Path(image_path).unlink(missing_ok=True)

    rows.sort(key=lambda row: (round(row["y"] / 12) * 12, row["x"]))

    return {
        "engine": "paddleocr",
        "text": "\n".join(row["text"] for row in rows),
        "items": rows,
    }


def run_ocr(image_path: str) -> list[dict[str, Any]]:
    predict = getattr(ocr_engine, "predict", None)

    if callable(predict):
        try:
            result = predict(
                image_path,
                use_doc_orientation_classify=False,
                use_doc_unwarping=False,
                use_textline_orientation=False,
            )
        except TypeError:
            result = predict(image_path)

        return rows_from_prediction_result(result)

    result = ocr_engine.ocr(image_path, cls=False)

    return rows_from_legacy_result(result)


def optimize_image(image_path: str) -> None:
    max_side = env_int("PADDLEOCR_MAX_IMAGE_SIDE")

    if max_side is None or max_side <= 0:
        return

    try:
        with Image.open(image_path) as image:
            image = ImageOps.exif_transpose(image)
            width, height = image.size
            largest_side = max(width, height)

            if largest_side <= max_side:
                return

            scale = max_side / largest_side
            resized = image.resize((round(width * scale), round(height * scale)), Image.Resampling.LANCZOS)

            if resized.mode not in ("RGB", "L"):
                resized = resized.convert("RGB")

            resized.save(image_path, format="JPEG", quality=env_int("PADDLEOCR_IMAGE_QUALITY") or 90, optimize=True)
    except Exception:
        return


def rows_from_prediction_result(result: Any) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []

    for page in ensure_list(result):
        data = result_to_dict(page)
        payload = data.get("res", data)
        texts = payload.get("rec_texts") or payload.get("texts") or []
        scores = payload.get("rec_scores") or payload.get("scores") or []
        boxes = payload.get("rec_polys") or payload.get("dt_polys") or payload.get("rec_boxes") or []

        for index, text in enumerate(texts):
            row = row_from_parts(
                text,
                scores[index] if index < len(scores) else None,
                boxes[index] if index < len(boxes) else None,
            )

            if row is not None:
                rows.append(row)

    return rows


def rows_from_legacy_result(result: Any) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []

    for entry in flatten_legacy_entries(result):
        if not isinstance(entry, (list, tuple)) or len(entry) < 2:
            continue

        box = entry[0]
        text_score = entry[1]

        if isinstance(text_score, (list, tuple)) and len(text_score) >= 2:
            text = text_score[0]
            score = text_score[1]
        else:
            text = text_score
            score = None

        row = row_from_parts(text, score, box)

        if row is not None:
            rows.append(row)

    return rows


def flatten_legacy_entries(result: Any) -> list[Any]:
    if not isinstance(result, list):
        return []

    if result and isinstance(result[0], list) and result[0] and is_ocr_entry(result[0][0]):
        return result[0]

    return result


def is_ocr_entry(value: Any) -> bool:
    return isinstance(value, (list, tuple)) and len(value) >= 2


def row_from_parts(text: Any, score: Any, box: Any) -> dict[str, Any] | None:
    normalized_text = str(text or "").strip()

    if normalized_text == "":
        return None

    points = box_points(box)
    xs = [point[0] for point in points] or [0.0]
    ys = [point[1] for point in points] or [0.0]

    return {
        "x": min(xs),
        "y": min(ys),
        "text": normalized_text,
        "confidence": float(score) if isinstance(score, (float, int)) else None,
    }


def box_points(box: Any) -> list[tuple[float, float]]:
    if box is None:
        return []

    if hasattr(box, "tolist"):
        box = box.tolist()

    if isinstance(box, (list, tuple)) and len(box) == 4 and all(is_number(value) for value in box):
        left, top, right, bottom = [float(value) for value in box]

        return [(left, top), (right, top), (right, bottom), (left, bottom)]

    points = []

    for point in ensure_list(box):
        if hasattr(point, "tolist"):
            point = point.tolist()

        if isinstance(point, (list, tuple)) and len(point) >= 2 and is_number(point[0]) and is_number(point[1]):
            points.append((float(point[0]), float(point[1])))

    return points


def result_to_dict(result: Any) -> dict[str, Any]:
    if isinstance(result, dict):
        return result

    json_payload = getattr(result, "json", None)

    if isinstance(json_payload, dict):
        return json_payload

    to_dict = getattr(result, "to_dict", None)

    if callable(to_dict):
        converted = to_dict()

        if isinstance(converted, dict):
            return converted

    return {}


def ensure_list(value: Any) -> list[Any]:
    if value is None:
        return []

    if isinstance(value, list):
        return value

    if isinstance(value, tuple):
        return list(value)

    return [value]


def is_number(value: Any) -> bool:
    return isinstance(value, (float, int))


def env_int(name: str) -> int | None:
    value = os.environ.get(name)

    if value is None or value.strip() == "":
        return None

    try:
        return int(value)
    except ValueError:
        return None


def env_float(name: str) -> float | None:
    value = os.environ.get(name)

    if value is None or value.strip() == "":
        return None

    try:
        return float(value)
    except ValueError:
        return None
