/**
 * `x-compress` — on a file <input>, shrink selected images to <= 2 MB before upload by
 * re-encoding (and downscaling oversized ones) to JPEG on a canvas. Registered in app.js.
 *
 * The compressed files replace input.files via a DataTransfer, so the normal form POST sends
 * the smaller versions — no bundler or network work at submit time. Non-image files (PDFs) and
 * animated GIFs pass through untouched, and if compression wouldn't actually shrink the file we
 * keep the original. This pairs with the server-side ImageUnderSize(2048) rule as a backstop.
 */
const MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const MAX_DIM = 2000;              // cap the longest edge to tame phone-camera photos

function canvasToBlob(canvas, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

async function compressImage(file) {
    // Skip non-images and GIFs (canvas would flatten the animation), and anything already small.
    if (! file.type.startsWith('image/') || file.type === 'image/gif' || file.size <= MAX_BYTES) {
        return file;
    }

    const bitmap = await createImageBitmap(file).catch(() => null);
    if (! bitmap) {
        return file;
    }

    const scale = Math.min(1, MAX_DIM / Math.max(bitmap.width, bitmap.height));
    const width = Math.round(bitmap.width * scale);
    const height = Math.round(bitmap.height * scale);

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0, width, height);
    bitmap.close?.();

    // Step quality down until under the cap (or we bottom out at a reasonable floor).
    let quality = 0.9;
    let blob = await canvasToBlob(canvas, quality);
    while (blob && blob.size > MAX_BYTES && quality > 0.4) {
        quality -= 0.1;
        blob = await canvasToBlob(canvas, quality);
    }

    if (! blob || blob.size >= file.size) {
        return file; // no real gain — keep the original
    }

    const name = file.name.replace(/\.(png|webp|bmp|tiff?|heic|heif)$/i, '.jpg');
    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

/**
 * One delegated listener on the document handles every `<input type="file" data-compress>` —
 * including inputs added later (e.g. the asset form's x-for media rows) and forms that live
 * outside any Alpine scope (the asset-detail photo/attachment forms). Reassigning input.files
 * does not re-fire change, so there is no loop.
 */
export default function registerImageCompress() {
    document.addEventListener('change', async (event) => {
        const el = event.target;
        if (! (el instanceof HTMLInputElement) || el.type !== 'file' || ! el.hasAttribute('data-compress')) {
            return;
        }
        if (! el.files || el.files.length === 0) {
            return;
        }

        const transfer = new DataTransfer();
        let changed = false;
        for (const file of Array.from(el.files)) {
            const out = await compressImage(file);
            if (out !== file) {
                changed = true;
            }
            transfer.items.add(out);
        }

        if (changed) {
            el.files = transfer.files;
        }
    });
}
