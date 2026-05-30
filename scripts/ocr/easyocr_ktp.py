#!/usr/bin/env python3
from contextlib import redirect_stdout
import json
import os
import sys
from pathlib import Path


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: easyocr_ktp.py IMAGE_PATH", file=sys.stderr)
        return 2

    image_path = Path(sys.argv[1])

    if not image_path.is_file():
        print(f"Image not found: {image_path}", file=sys.stderr)
        return 2

    try:
        import easyocr
    except Exception as exc:
        print(f"EasyOCR is not installed or could not be imported: {exc}", file=sys.stderr)
        return 1

    languages = ["id", "en"]
    model_directory = os.environ.get("EASYOCR_MODEL_DIR")
    reader_kwargs = {"gpu": False, "verbose": False}

    if model_directory:
        Path(model_directory).mkdir(parents=True, exist_ok=True)
        reader_kwargs["model_storage_directory"] = model_directory

    with redirect_stdout(sys.stderr):
        reader = easyocr.Reader(languages, **reader_kwargs)
        results = reader.readtext(str(image_path), detail=1, paragraph=False)

    rows = []

    for box, text, confidence in results:
        if not text or not str(text).strip():
            continue

        xs = [float(point[0]) for point in box]
        ys = [float(point[1]) for point in box]
        rows.append(
            {
                "x": min(xs),
                "y": min(ys),
                "text": str(text).strip(),
                "confidence": float(confidence),
            }
        )

    rows.sort(key=lambda row: (round(row["y"] / 12) * 12, row["x"]))

    print(
        json.dumps(
            {
                "languages": languages,
                "text": "\n".join(row["text"] for row in rows),
                "items": rows,
            },
            ensure_ascii=True,
        )
    )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
