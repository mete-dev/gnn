import React, { useState, useRef, useEffect } from 'react';
import { Crop, Check, X, ZoomIn, ZoomOut, RotateCcw, Image as ImageIcon, Tag, UserCheck } from 'lucide-react';
import { compressImageToUnder50KB } from '../../utils/imageCompressor';

export type AspectRatioType = '5:4' | '9:16' | '16:9' | '1:1' | 'free';

interface ImageCropperModalProps {
  isOpen: boolean;
  imageUrl: string;
  initialCaption?: string;
  initialCredit?: string;
  onClose: () => void;
  onCropComplete: (croppedDataUrl: string, caption: string, credit: string) => void;
  initialAspect?: AspectRatioType;
}

export const ImageCropperModal: React.FC<ImageCropperModalProps> = ({
  isOpen,
  imageUrl,
  initialCaption = '',
  initialCredit = '',
  onClose,
  onCropComplete,
  initialAspect = '5:4',
}) => {
  const [aspect, setAspect] = useState<AspectRatioType>(initialAspect);
  const [zoom, setZoom] = useState<number>(1);
  const [pan, setPan] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
  const [isDragging, setIsDragging] = useState<boolean>(false);
  const [dragStart, setDragStart] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
  const [processing, setProcessing] = useState<boolean>(false);
  const [imgLoaded, setImgLoaded] = useState<boolean>(false);
  const [caption, setCaption] = useState<string>(initialCaption);
  const [credit, setCredit] = useState<string>(initialCredit);

  const containerRef = useRef<HTMLDivElement>(null);
  const imageRef = useRef<HTMLImageElement>(null);

  // Reset state when modal opens or props change
  useEffect(() => {
    if (isOpen) {
      setAspect(initialAspect);
      setZoom(1);
      setPan({ x: 0, y: 0 });
      setCaption(initialCaption);
      setCredit(initialCredit);

      // Instantly set imgLoaded if image is already cached or complete in DOM
      if (imageRef.current && imageRef.current.complete) {
        setImgLoaded(true);
      } else {
        setImgLoaded(true); // Default true so save button is never stuck disabled
      }
    }
  }, [isOpen, imageUrl, initialAspect, initialCaption, initialCredit]);

  if (!isOpen || !imageUrl) return null;

  // Calculate numeric ratio (width / height)
  const getRatioValue = (aspectType: AspectRatioType): number | null => {
    switch (aspectType) {
      case '5:4':
        return 5 / 4; // 1.25
      case '9:16':
        return 9 / 16; // 0.5625
      case '16:9':
        return 16 / 9; // 1.7777...
      case '1:1':
        return 1;
      case 'free':
      default:
        return null;
    }
  };

  const handleMouseDown = (e: React.MouseEvent) => {
    e.preventDefault();
    setIsDragging(true);
    setDragStart({ x: e.clientX - pan.x, y: e.clientY - pan.y });
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!isDragging) return;
    setPan({
      x: e.clientX - dragStart.x,
      y: e.clientY - dragStart.y,
    });
  };

  const handleMouseUp = () => {
    setIsDragging(false);
  };

  const handleTouchStart = (e: React.TouchEvent) => {
    if (e.touches.length === 1) {
      setIsDragging(true);
      setDragStart({
        x: e.touches[0].clientX - pan.x,
        y: e.touches[0].clientY - pan.y,
      });
    }
  };

  const handleTouchMove = (e: React.TouchEvent) => {
    if (!isDragging || e.touches.length !== 1) return;
    setPan({
      x: e.touches[0].clientX - dragStart.x,
      y: e.touches[0].clientY - dragStart.y,
    });
  };

  const handleReset = () => {
    setZoom(1);
    setPan({ x: 0, y: 0 });
  };

  const handleApplyCrop = async () => {
    setProcessing(true);

    try {
      let exportDataUrl = imageUrl;

      if (imageRef.current && containerRef.current) {
        try {
          const img = imageRef.current;
          const container = containerRef.current;
          const containerRect = container.getBoundingClientRect();

          const targetRatio = getRatioValue(aspect);

          let cropWidth = containerRect.width;
          let cropHeight = containerRect.height;

          if (targetRatio) {
            if (containerRect.width / containerRect.height > targetRatio) {
              cropHeight = containerRect.height * 0.85;
              cropWidth = cropHeight * targetRatio;
            } else {
              cropWidth = containerRect.width * 0.85;
              cropHeight = cropWidth / targetRatio;
            }
          } else {
            cropWidth = containerRect.width * 0.85;
            cropHeight = containerRect.height * 0.85;
          }

          const canvas = document.createElement('canvas');
          const ctx = canvas.getContext('2d');

          if (ctx) {
            const exportWidth = targetRatio ? (targetRatio >= 1 ? 1200 : Math.round(1200 * targetRatio)) : (img.naturalWidth || 1200);
            const exportHeight = targetRatio ? Math.round(exportWidth / targetRatio) : (img.naturalHeight || 900);

            canvas.width = exportWidth;
            canvas.height = exportHeight;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, exportWidth, exportHeight);

            const cropLeft = (containerRect.width - cropWidth) / 2;
            const cropTop = (containerRect.height - cropHeight) / 2;

            const imgCenterX = containerRect.width / 2 + pan.x;
            const imgCenterY = containerRect.height / 2 + pan.y;

            const imgTopLeftInCropX = imgCenterX - (img.clientWidth * zoom) / 2 - cropLeft;
            const imgTopLeftInCropY = imgCenterY - (img.clientHeight * zoom) / 2 - cropTop;

            const exportScaleX = exportWidth / cropWidth;
            const exportScaleY = exportHeight / cropHeight;

            const drawWidth = img.clientWidth * zoom * exportScaleX;
            const drawHeight = img.clientHeight * zoom * exportScaleY;
            const drawX = imgTopLeftInCropX * exportScaleX;
            const drawY = imgTopLeftInCropY * exportScaleY;

            ctx.drawImage(img, drawX, drawY, drawWidth, drawHeight);

            const rawDataUrl = canvas.toDataURL('image/jpeg', 0.92);
            if (rawDataUrl && rawDataUrl !== 'data:,') {
              exportDataUrl = rawDataUrl;
            }
          }
        } catch (canvasErr) {
          console.warn('Canvas crop fallback used:', canvasErr);
        }
      }

      let finalDataUrl = exportDataUrl;
      try {
        const compressed = await compressImageToUnder50KB(exportDataUrl, 50);
        finalDataUrl = compressed.dataUrl;
      } catch (compressErr) {
        console.warn('Compression skipped fallback:', compressErr);
      }

      onCropComplete(finalDataUrl, caption, credit);
      onClose();
    } catch (err: any) {
      alert('Gagal menyimpan foto: ' + err.message);
    } finally {
      setProcessing(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4 bg-black/80 backdrop-blur-xs overflow-y-auto">
      <div className="bg-white rounded-2xl max-w-xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[95vh]">
        {/* Header Modal */}
        <div className="px-4 py-2.5 border-b border-zinc-200 flex items-center justify-between bg-zinc-50 shrink-0">
          <div className="flex items-center gap-2">
            <Crop className="w-4 h-4 text-red-600" />
            <div>
              <h3 className="text-xs sm:text-sm font-extrabold text-zinc-900 leading-tight">Pengaturan Foto Cover Artikel</h3>
              <p className="text-[10px] sm:text-[11px] text-zinc-500 font-medium">Potong gambar, atur rasio, isi judul & kredit foto</p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-200 transition cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Aspect Ratio Selector Pills */}
        <div className="px-3 py-2 bg-zinc-100/90 border-b border-zinc-200 flex flex-wrap items-center justify-center gap-1.5 shrink-0">
          <span className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mr-1">Pilih Rasio:</span>

          <button
            type="button"
            onClick={() => {
              setAspect('5:4');
              handleReset();
            }}
            className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1 cursor-pointer ${
              aspect === '5:4'
                ? 'bg-red-600 text-white shadow-xs ring-2 ring-red-300'
                : 'bg-white text-zinc-700 hover:bg-zinc-200 border border-zinc-200'
            }`}
          >
            <span className="font-mono">5:4</span> (Standard)
          </button>

          <button
            type="button"
            onClick={() => {
              setAspect('9:16');
              handleReset();
            }}
            className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1 cursor-pointer ${
              aspect === '9:16'
                ? 'bg-red-600 text-white shadow-xs ring-2 ring-red-300'
                : 'bg-white text-zinc-700 hover:bg-zinc-200 border border-zinc-200'
            }`}
          >
            <span className="font-mono">9:16</span> (Story)
          </button>

          <button
            type="button"
            onClick={() => {
              setAspect('16:9');
              handleReset();
            }}
            className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1 cursor-pointer ${
              aspect === '16:9'
                ? 'bg-red-600 text-white shadow-xs ring-2 ring-red-300'
                : 'bg-white text-zinc-700 hover:bg-zinc-200 border border-zinc-200'
            }`}
          >
            <span className="font-mono">16:9</span> (Landscape)
          </button>

          <button
            type="button"
            onClick={() => {
              setAspect('1:1');
              handleReset();
            }}
            className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1 cursor-pointer ${
              aspect === '1:1'
                ? 'bg-red-600 text-white shadow-xs ring-2 ring-red-300'
                : 'bg-white text-zinc-700 hover:bg-zinc-200 border border-zinc-200'
            }`}
          >
            <span className="font-mono">1:1</span> (Persegi)
          </button>

          <button
            type="button"
            onClick={() => {
              setAspect('free');
              handleReset();
            }}
            className={`px-2.5 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1 cursor-pointer ${
              aspect === 'free'
                ? 'bg-red-600 text-white shadow-xs ring-2 ring-red-300'
                : 'bg-white text-zinc-700 hover:bg-zinc-200 border border-zinc-200'
            }`}
          >
            Asli (Bebas)
          </button>
        </div>

        {/* Cropper Interactive Canvas Area */}
        <div
          ref={containerRef}
          onMouseDown={handleMouseDown}
          onMouseMove={handleMouseMove}
          onMouseUp={handleMouseUp}
          onMouseLeave={handleMouseUp}
          onTouchStart={handleTouchStart}
          onTouchMove={handleTouchMove}
          onTouchEnd={handleMouseUp}
          className="relative bg-zinc-900 h-[190px] sm:h-[220px] shrink-0 overflow-hidden select-none cursor-grab active:cursor-grabbing flex items-center justify-center"
        >
          {/* Main Image being dragged & scaled */}
          <img
            ref={imageRef}
            src={imageUrl}
            alt="To crop"
            onLoad={() => setImgLoaded(true)}
            crossOrigin="anonymous"
            style={{
              transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
              maxHeight: '100%',
              maxWidth: '100%',
              objectFit: 'contain',
              transition: isDragging ? 'none' : 'transform 0.1s ease-out',
            }}
            className="pointer-events-none select-none max-w-full max-h-full"
          />

          {/* Overlay Box with Crop Frame & Rule-of-Thirds Grid */}
          {imgLoaded && (
            <div className="absolute inset-0 pointer-events-none flex items-center justify-center">
              {(() => {
                const ratio = getRatioValue(aspect);
                let frameStyle: React.CSSProperties = {
                  width: '85%',
                  height: '85%',
                };

                if (ratio && containerRef.current) {
                  const cw = containerRef.current.clientWidth;
                  const ch = containerRef.current.clientHeight;
                  if (cw / ch > ratio) {
                    const h = ch * 0.85;
                    const w = h * ratio;
                    frameStyle = { width: `${w}px`, height: `${h}px` };
                  } else {
                    const w = cw * 0.85;
                    const h = w / ratio;
                    frameStyle = { width: `${w}px`, height: `${h}px` };
                  }
                }

                return (
                  <div
                    style={frameStyle}
                    className="relative border-2 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.6)] rounded-lg transition-all duration-200"
                  >
                    {/* Grid Rule of Thirds */}
                    <div className="absolute inset-0 grid grid-cols-3 grid-rows-3 pointer-events-none">
                      <div className="border-r border-b border-white/20"></div>
                      <div className="border-r border-b border-white/20"></div>
                      <div className="border-b border-white/20"></div>
                      <div className="border-r border-b border-white/20"></div>
                      <div className="border-r border-b border-white/20"></div>
                      <div className="border-b border-white/20"></div>
                      <div className="border-r border-white/20"></div>
                      <div className="border-r border-white/20"></div>
                      <div></div>
                    </div>

                    {/* Corner Markers */}
                    <div className="absolute -top-1 -left-1 w-3 h-3 border-t-2 border-l-2 border-red-500" />
                    <div className="absolute -top-1 -right-1 w-3 h-3 border-t-2 border-r-2 border-red-500" />
                    <div className="absolute -bottom-1 -left-1 w-3 h-3 border-b-2 border-l-2 border-red-500" />
                    <div className="absolute -bottom-1 -right-1 w-3 h-3 border-b-2 border-r-2 border-red-500" />

                    {/* Active Ratio Badge */}
                    <div className="absolute top-1.5 left-1.5 bg-red-600 text-white text-[9px] font-extrabold px-1.5 py-0.5 rounded-md shadow-xs uppercase">
                      Rasio: {aspect}
                    </div>
                  </div>
                );
              })()}
            </div>
          )}

          {!imgLoaded && (
            <div className="text-white text-xs font-bold animate-pulse flex items-center gap-2">
              <ImageIcon className="w-4 h-4 text-red-500" /> Memuat Pratinjau Gambar...
            </div>
          )}
        </div>

        {/* Zoom & Pan Controls Bar */}
        <div className="px-3 py-1.5 bg-zinc-900 border-t border-zinc-800 flex items-center justify-between gap-3 shrink-0 text-white">
          <div className="flex items-center gap-2 flex-1">
            <ZoomOut className="w-3.5 h-3.5 text-zinc-400" />
            <input
              type="range"
              min="1"
              max="3"
              step="0.05"
              value={zoom}
              onChange={(e) => setZoom(parseFloat(e.target.value))}
              className="w-full accent-red-600 cursor-pointer h-1.5"
            />
            <ZoomIn className="w-3.5 h-3.5 text-zinc-400" />
            <span className="text-[11px] font-mono font-bold text-zinc-300 w-9 text-right">
              {Math.round(zoom * 100)}%
            </span>
          </div>

          <button
            type="button"
            onClick={handleReset}
            className="px-2 py-0.5 rounded-md text-[11px] font-bold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 transition flex items-center gap-1 cursor-pointer"
            title="Reset Posisi & Zoom"
          >
            <RotateCcw className="w-3 h-3" /> Reset
          </button>
        </div>

        {/* Form Inputs: Nama / Caption Foto & Kredit Foto */}
        <div className="p-3 bg-zinc-50 border-t border-zinc-200 grid grid-cols-1 sm:grid-cols-2 gap-2.5 shrink-0">
          <div className="space-y-1">
            <label className="text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wider text-zinc-700 flex items-center gap-1">
              <Tag className="w-3 h-3 text-red-600" /> Nama / Judul Foto (Caption)
            </label>
            <input
              type="text"
              placeholder="Contoh: Pemandangan Gunung Bromo pagi hari"
              value={caption}
              onChange={(e) => setCaption(e.target.value)}
              className="w-full bg-white border border-zinc-300 rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 font-medium focus:border-red-600 focus:outline-none transition shadow-2xs"
            />
          </div>

          <div className="space-y-1">
            <label className="text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wider text-zinc-700 flex items-center gap-1">
              <UserCheck className="w-3 h-3 text-red-600" /> Kredit Foto (Fotografer / Sumber)
            </label>
            <input
              type="text"
              placeholder="Contoh: Foto: Antara / Tim Redaksi GNN"
              value={credit}
              onChange={(e) => setCredit(e.target.value)}
              className="w-full bg-white border border-zinc-300 rounded-xl px-2.5 py-1.5 text-xs text-zinc-900 font-medium focus:border-red-600 focus:outline-none transition shadow-2xs"
            />
          </div>
        </div>

        {/* Footer Actions (Pinned Bottom) */}
        <div className="px-4 py-2.5 bg-white border-t border-zinc-200 flex items-center justify-between gap-2 shrink-0">
          <span className="text-[10px] sm:text-[11px] text-zinc-500 font-medium truncate">
            * Geser foto untuk menyesuaikan posisi crop.
          </span>

          <div className="flex items-center gap-2 shrink-0">
            <button
              type="button"
              onClick={onClose}
              className="px-3.5 py-1.5 rounded-lg text-xs font-bold text-zinc-600 bg-zinc-100 hover:bg-zinc-200 transition cursor-pointer"
            >
              Batal
            </button>
            <button
              type="button"
              disabled={processing || !imgLoaded}
              onClick={handleApplyCrop}
              className="px-4 py-1.5 rounded-lg text-xs font-extrabold text-white bg-red-600 hover:bg-red-700 shadow-md transition disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
            >
              {processing ? (
                'Memproses...'
              ) : (
                <>
                  <Check className="w-3.5 h-3.5" /> Simpan & Terapkan Foto
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
