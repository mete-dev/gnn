import React, { useState, useRef } from 'react';
import {
  Image as ImageIcon,
  Upload,
  Copy,
  Check,
  Trash2,
  Share2,
  Sparkles,
  Info,
  Search,
  Plus,
  Zap,
  PanelLeftClose,
  PanelLeftOpen,
  Menu,
  ExternalLink
} from 'lucide-react';
import { GalleryItem, User } from '../../types/studio';
import { compressImageToUnder50KB } from '../../utils/imageCompressor';

interface GalleryManagerProps {
  items: GalleryItem[];
  currentUser: User;
  onUploadImage: (item: Partial<GalleryItem>) => Promise<void>;
  onDeleteItem: (id: string) => Promise<void>;
  onToggleSidebar?: () => void;
  isCollapsed?: boolean;
  onOpenMobileSidebar?: () => void;
  onBackToPortal?: () => void;
}

export const GalleryManager: React.FC<GalleryManagerProps> = ({
  items,
  currentUser,
  onUploadImage,
  onDeleteItem,
  onToggleSidebar,
  isCollapsed,
  onOpenMobileSidebar,
  onBackToPortal,
}) => {
  const [search, setSearch] = useState('');
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [copiedId, setCopiedId] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const [compressionStatus, setCompressionStatus] = useState<string | null>(null);

  // Form states
  const [title, setTitle] = useState('');
  const [caption, setCaption] = useState('');
  const [previewDataUrl, setPreviewDataUrl] = useState<string | null>(null);
  const [compressedKb, setCompressedKb] = useState<number | null>(null);
  const [originalKb, setOriginalKb] = useState<number | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading(true);
    setCompressionStatus('Proses Kompresi Otomatis ke < 50KB...');

    try {
      const res = await compressImageToUnder50KB(file, 50);
      setPreviewDataUrl(res.dataUrl);
      setCompressedKb(res.sizeKb);
      setOriginalKb(res.originalSizeKb);
      if (!title) setTitle(file.name.replace(/\.[^/.]+$/, ''));
      setCompressionStatus(
        `✓ Hasil Kompresi: ${res.originalSizeKb} KB ➔ ${res.sizeKb} KB (<50KB & Tetap Jernih!)`
      );
    } catch (err: any) {
      alert('Gagal memproses gambar: ' + err.message);
      setCompressionStatus(null);
    } finally {
      setUploading(false);
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!previewDataUrl) return;

    try {
      await onUploadImage({
        title: title || 'Foto Tanpa Judul',
        caption: caption || '',
        imageUrl: previewDataUrl,
        fileSizeKb: compressedKb || 45,
        author: currentUser.name,
        authorEmail: currentUser.email,
        date: new Date().toLocaleDateString('id-ID', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        }),
      });

      // Reset form
      setTitle('');
      setCaption('');
      setPreviewDataUrl(null);
      setCompressedKb(null);
      setOriginalKb(null);
      setCompressionStatus(null);
      setShowUploadModal(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    } catch (err: any) {
      alert('Gagal mengunggah foto: ' + err.message);
    }
  };

  const copyUrl = (url: string, id: string) => {
    navigator.clipboard.writeText(url);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const filteredItems = items.filter(
    (item) =>
      item.title.toLowerCase().includes(search.toLowerCase()) ||
      item.caption?.toLowerCase().includes(search.toLowerCase()) ||
      item.author.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-4">
      {/* 1 TRULY UNIFIED SINGLE-LINE STICKY TOP HEADER BAR */}
      <div className="sticky top-0 z-30 -mx-4 sm:-mx-6 mb-6 h-[57px] bg-white px-4 sm:px-6 border-b border-zinc-200 shadow-2xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 shrink-0">
        {/* Left Side: Sidebar Toggle & Page Title */}
        <div className="flex items-center gap-2.5">
          {onOpenMobileSidebar && (
            <button
              onClick={onOpenMobileSidebar}
              className="md:hidden p-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition"
              title="Buka Navigasi Mobile"
            >
              <Menu className="w-5 h-5 text-red-600" />
            </button>
          )}

          {onToggleSidebar && (
            <button
              onClick={onToggleSidebar}
              className="hidden md:flex p-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer shrink-0"
              title={isCollapsed ? 'Buka Sidebar' : 'Tutup Sidebar'}
            >
              <ImageIcon className="w-5 h-5 text-red-600" />
            </button>
          )}

          <h2 className="text-sm sm:text-base font-black text-zinc-900 tracking-tight whitespace-nowrap">
            Galeri Foto Redaksi
          </h2>
        </div>

        {/* Right Side: Search, + Unggah Foto Button & Lihat Situs Publik */}
        <div className="flex items-center gap-2 w-full md:w-auto justify-end">
          <div className="relative flex-1 md:w-56 min-w-[150px]">
            <Search className="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Cari di galeri foto..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-zinc-50 border border-zinc-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
            />
          </div>

          <button
            onClick={() => setShowUploadModal(!showUploadModal)}
            className="px-3.5 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-2xs transition shrink-0 cursor-pointer whitespace-nowrap"
          >
            <Plus className="w-4 h-4" /> Unggah Foto
          </button>
        </div>
      </div>

      {/* Upload Box (Only Visible when showUploadModal is True) */}
      {showUploadModal && (
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-xl space-y-4">
          <div className="flex items-center justify-between border-b border-zinc-100 pb-3">
            <h3 className="text-sm font-bold text-zinc-900 flex items-center gap-2">
              <Upload className="w-4 h-4 text-emerald-600" /> Unggah & Kompres Foto Baru (&lt; 50KB)
            </h3>
            <button
              onClick={() => setShowUploadModal(false)}
              className="px-3 py-1 text-xs font-bold text-zinc-600 bg-zinc-100 hover:bg-zinc-200 rounded-lg cursor-pointer"
            >
              Tutup
            </button>
          </div>

        <form onSubmit={handleSave} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* File drop / button */}
            <div
              className="flex flex-col items-center justify-center border-2 border-dashed border-zinc-300 hover:border-red-500 rounded-2xl p-6 bg-zinc-50 hover:bg-red-50/20 transition cursor-pointer relative"
              onClick={() => fileInputRef.current?.click()}
            >
              <input
                type="file"
                ref={fileInputRef}
                onChange={handleFileChange}
                accept="image/*"
                className="hidden"
              />
              <Upload className="w-8 h-8 text-zinc-400 mb-2" />
              <p className="text-xs font-bold text-zinc-800">Klik untuk Pilih Foto dari Perangkat</p>
              <p className="text-[11px] text-zinc-500 mt-1">Sistem akan otomatis mengompres foto hingga &lt; 50KB</p>

              {uploading && (
                <div className="absolute inset-0 bg-white/90 backdrop-blur-xs flex items-center justify-center rounded-2xl text-xs text-red-600 font-bold gap-2">
                  <Zap className="w-4 h-4 animate-bounce" /> Memproses Kompresi...
                </div>
              )}
            </div>

            {/* Preview & Details */}
            {previewDataUrl ? (
              <div className="space-y-3 bg-zinc-50 p-4 rounded-2xl border border-zinc-200">
                <img
                  src={previewDataUrl}
                  alt="Preview"
                  className="w-full h-32 object-cover rounded-xl border border-zinc-200"
                />
                <div className="flex items-center justify-between text-xs text-emerald-700 font-mono font-bold bg-emerald-50 p-2 rounded-lg border border-emerald-200">
                  <span>Ukuran Asli: {originalKb} KB</span>
                  <span>➔ Hasil Kompresi: {compressedKb} KB (Tajam)</span>
                </div>
              </div>
            ) : (
              <div className="flex items-center justify-center bg-zinc-50 p-4 rounded-2xl border border-zinc-200 text-zinc-400 text-xs italic">
                Pratinjau hasil kompresi gambar akan muncul di sini...
              </div>
            )}
          </div>

          {compressionStatus && (
            <div className="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 p-2.5 rounded-xl">
              {compressionStatus}
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input
              type="text"
              required
              placeholder="Judul / Nama Foto..."
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
            />
            <input
              type="text"
              placeholder="Keterangan / Caption Foto..."
              value={caption}
              onChange={(e) => setCaption(e.target.value)}
              className="bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
            />
          </div>

          <div className="flex justify-end">
            <button
              type="submit"
              disabled={!previewDataUrl}
              className="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold text-xs shadow-2xs flex items-center gap-2 transition cursor-pointer"
            >
              <Plus className="w-4 h-4" /> Simpan ke Galeri Foto
            </button>
          </div>
        </form>
      </div>
      )}

      {/* Gallery Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        {filteredItems.map((item) => (
          <div
            key={item.id}
            className="bg-white border border-zinc-200 hover:border-zinc-300 rounded-2xl overflow-hidden shadow-2xs transition flex flex-col justify-between group"
          >
            <div>
              <div className="relative aspect-video bg-zinc-100 overflow-hidden">
                <img
                  src={item.imageUrl}
                  alt={item.title}
                  className="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                />
                <div className="absolute top-2 right-2 bg-black/70 backdrop-blur-xs text-emerald-400 px-2 py-0.5 rounded text-[10px] font-mono font-bold">
                  {item.fileSizeKb} KB
                </div>
              </div>
              <div className="p-3.5 space-y-1">
                <h4 className="text-xs font-bold text-zinc-900 line-clamp-1">{item.title}</h4>
                {item.caption && (
                  <p className="text-[11px] text-zinc-500 line-clamp-2">{item.caption}</p>
                )}
                <div className="text-[10px] text-zinc-400 pt-1 flex items-center justify-between font-medium">
                  <span>Oleh: {item.author}</span>
                  <span>{item.date}</span>
                </div>
              </div>
            </div>

            <div className="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center justify-between gap-2">
              <button
                onClick={() => copyUrl(item.imageUrl, item.id)}
                className="flex-1 py-1.5 rounded-lg bg-zinc-200 hover:bg-zinc-300 text-zinc-800 text-[11px] font-bold flex items-center justify-center gap-1.5 transition cursor-pointer"
                title="Salin URL Foto untuk digunakan di artikel"
              >
                {copiedId === item.id ? (
                  <>
                    <Check className="w-3.5 h-3.5 text-emerald-600" /> Disalin!
                  </>
                ) : (
                  <>
                    <Copy className="w-3.5 h-3.5" /> Salin Tautan Foto
                  </>
                )}
              </button>

              {(currentUser.role === 'admin' || currentUser.email === item.authorEmail) && (
                <button
                  onClick={() => onDeleteItem(item.id)}
                  className="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition cursor-pointer"
                  title="Hapus dari galeri"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
