/**
 * Utility to compress images to under 50 KB while maintaining high clarity.
 * Uses HTML5 Canvas downscaling & adaptive quality iteration.
 */

export interface CompressionResult {
  dataUrl: string;
  sizeKb: number;
  originalSizeKb: number;
  width: number;
  height: number;
  qualityUsed: number;
}

export async function compressImageToUnder50KB(
  fileOrUrl: File | string,
  maxKb: number = 50
): Promise<CompressionResult> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';

    const getOriginalSize = (source: File | string): number => {
      if (source instanceof File) {
        return Math.round(source.size / 1024);
      }
      if (typeof source === 'string' && source.startsWith('data:')) {
        return Math.round((source.length * 0.75) / 1024);
      }
      return 100; // Estimated for remote URL
    };

    const originalSizeKb = getOriginalSize(fileOrUrl);

    img.onload = () => {
      let width = img.naturalWidth || img.width;
      let height = img.naturalHeight || img.height;

      // Initial scaling down if dimensions are huge
      const MAX_DIMENSION = 1200;
      if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
        if (width > height) {
          height = Math.round((height * MAX_DIMENSION) / width);
          width = MAX_DIMENSION;
        } else {
          width = Math.round((width * MAX_DIMENSION) / height);
          height = MAX_DIMENSION;
        }
      }

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');

      if (!ctx) {
        reject(new Error('Canvas 2D context not available'));
        return;
      }

      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';

      // Downscale factor loop if needed
      let currentWidth = width;
      let currentHeight = height;
      let quality = 0.88;
      let resultDataUrl = '';
      let currentSizeKb = Infinity;

      // Iterative loop: lower quality and scale if necessary to hit < 50KB
      let attempts = 0;
      while (currentSizeKb > maxKb && attempts < 15) {
        attempts++;
        canvas.width = currentWidth;
        canvas.height = currentHeight;

        // Draw image onto canvas
        ctx.clearRect(0, 0, currentWidth, currentHeight);
        ctx.drawImage(img, 0, 0, currentWidth, currentHeight);

        // Try WebP first for better compression quality, fallback to JPEG if needed
        resultDataUrl = canvas.toDataURL('image/webp', quality);
        if (!resultDataUrl.startsWith('data:image/webp')) {
          resultDataUrl = canvas.toDataURL('image/jpeg', quality);
        }

        // Calculate approximate KB size of base64
        currentSizeKb = Math.round((resultDataUrl.length * 0.75) / 1024);

        if (currentSizeKb <= maxKb) {
          break;
        }

        // Reduce quality slightly
        if (quality > 0.4) {
          quality -= 0.08;
        } else {
          // Downscale dimensions by 10%
          currentWidth = Math.round(currentWidth * 0.88);
          currentHeight = Math.round(currentHeight * 0.88);
        }
      }

      resolve({
        dataUrl: resultDataUrl,
        sizeKb: currentSizeKb,
        originalSizeKb,
        width: currentWidth,
        height: currentHeight,
        qualityUsed: Math.round(quality * 100) / 100,
      });
    };

    img.onerror = (err) => {
      reject(new Error('Failed to load image for compression'));
    };

    if (fileOrUrl instanceof File) {
      const reader = new FileReader();
      reader.onload = (e) => {
        img.src = e.target?.result as string;
      };
      reader.onerror = () => reject(new Error('Failed to read file'));
      reader.readAsDataURL(fileOrUrl);
    } else {
      img.src = fileOrUrl;
    }
  });
}
