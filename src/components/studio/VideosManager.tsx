import React, { useState } from 'react';
import {
  Video,
  Play,
  Plus,
  Trash2,
  Pencil,
  ExternalLink,
  Search,
  Film,
  Sparkles,
  Menu,
  PanelLeftClose,
  PanelLeftOpen
} from 'lucide-react';
import { User, VideoItem } from '../../types/studio';

interface VideosManagerProps {
  items: VideoItem[];
  currentUser: User;
  onAddVideo: (item: Partial<VideoItem>) => Promise<void>;
  onUpdateVideo?: (id: string, item: Partial<VideoItem>) => Promise<void>;
  onDeleteVideo: (id: string) => Promise<void>;
  onToggleSidebar?: () => void;
  isCollapsed?: boolean;
  onOpenMobileSidebar?: () => void;
  onBackToPortal?: () => void;
}

export const STUDIO_CATEGORY_MAP: Record<string, string[]> = {
  'EKONOMI': ['Ekonomi', 'Bisnis', 'Investasi', 'Ketenagakerjaan'],
  'SAINTEK': ['Pendidikan', 'Teknologi'],
  'LINGKUNGAN': ['Lingkungan'],
  'POLITIK': ['Kebijakan', 'Nasional', 'Asean', 'Internasional'],
  'HUKUM': ['Hukum', 'Kriminal', 'Investigasi'],
  'SOSIAL': ['Sosial', 'Budaya', 'Gastronomi', 'Seni & Sastra', 'Sejarah'],
  'LIFESTYLE': ['Religi', 'Olahraga', 'Hiburan', 'Kesehatan', 'Musik & Film'],
};

export const VideosManager: React.FC<VideosManagerProps> = ({
  items,
  currentUser,
  onAddVideo,
  onUpdateVideo,
  onDeleteVideo,
  onToggleSidebar,
  isCollapsed,
  onOpenMobileSidebar,
  onBackToPortal,
}) => {
  const [search, setSearch] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingVideo, setEditingVideo] = useState<VideoItem | null>(null);

  const [title, setTitle] = useState('');
  const [youtubeUrl, setYoutubeUrl] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState('EKONOMI');
  const [subCategory, setSubCategory] = useState('Ekonomi');
  const [duration, setDuration] = useState('04:15');

  const [activePlayId, setActivePlayId] = useState<string | null>(null);

  const handleAddNew = () => {
    setEditingVideo(null);
    setTitle('');
    setYoutubeUrl('');
    setDescription('');
    setCategory('EKONOMI');
    setSubCategory('Ekonomi');
    setDuration('04:15');
    setShowAddModal(true);
  };

  const handleEditClick = (item: VideoItem) => {
    setEditingVideo(item);
    setTitle(item.title || '');
    setYoutubeUrl(item.youtubeUrl || '');
    setDescription(item.description || '');
    setCategory(item.category || 'EKONOMI');
    setSubCategory(item.subCategory || 'Ekonomi');
    setDuration(item.duration || '04:15');
    setShowAddModal(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!title || !youtubeUrl) {
      alert('Judul dan Link YouTube wajib diisi!');
      return;
    }

    try {
      if (editingVideo && onUpdateVideo) {
        await onUpdateVideo(editingVideo.id, {
          title,
          youtubeUrl,
          description,
          category,
          subCategory,
          duration,
        });
      } else {
        await onAddVideo({
          title,
          youtubeUrl,
          description,
          category,
          subCategory,
          duration,
          author: currentUser.name,
          authorEmail: currentUser.email,
        });
      }

      setTitle('');
      setYoutubeUrl('');
      setDescription('');
      setEditingVideo(null);
      setShowAddModal(false);
    } catch (err: any) {
      alert('Gagal menyimpan data video: ' + err.message);
    }
  };

  const extractYoutubeEmbed = (url: string) => {
    let videoId = '';
    if (url.includes('youtube.com/watch?v=')) {
      videoId = url.split('v=')[1]?.split('&')[0] || '';
    } else if (url.includes('youtu.be/')) {
      videoId = url.split('youtu.be/')[1]?.split('?')[0] || '';
    } else if (url.includes('youtube.com/embed/')) {
      videoId = url.split('embed/')[1]?.split('?')[0] || '';
    }
    return videoId ? `https://www.youtube.com/embed/${videoId}` : url;
  };

  const filteredItems = items.filter(
    (item) =>
      item.title.toLowerCase().includes(search.toLowerCase()) ||
      item.description?.toLowerCase().includes(search.toLowerCase()) ||
      item.author.toLowerCase().includes(search.toLowerCase()) ||
      item.category.toLowerCase().includes(search.toLowerCase()) ||
      item.subCategory?.toLowerCase().includes(search.toLowerCase())
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
              <Video className="w-5 h-5 text-red-600" />
            </button>
          )}

          <h2 className="text-sm sm:text-base font-black text-zinc-900 tracking-tight whitespace-nowrap">
            Kelola Video YouTube GNN TV
          </h2>
        </div>

        {/* Right Side: Search, Tambah Video & Lihat Situs Publik */}
        <div className="flex items-center gap-2 w-full md:w-auto justify-end">
          <div className="relative flex-1 md:w-56 min-w-[150px]">
            <Search className="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Cari video YouTube..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-zinc-50 border border-zinc-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
            />
          </div>

          <button
            onClick={handleAddNew}
            className="px-3.5 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-2xs transition shrink-0 cursor-pointer whitespace-nowrap"
          >
            <Plus className="w-4 h-4" /> Tambah Video
          </button>
        </div>
      </div>

      {/* Add / Edit Modal */}
      {showAddModal && (
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-xl space-y-4">
          <div className="flex items-center justify-between border-b border-zinc-100 pb-3">
            <h3 className="text-sm font-bold text-zinc-900 flex items-center gap-2">
              <Film className="w-4 h-4 text-red-600" />
              <span>{editingVideo ? 'Edit Data Video YouTube GNN TV' : 'Tambah Pemutar Video YouTube Baru'}</span>
            </h3>
            <button
              onClick={() => {
                setShowAddModal(false);
                setEditingVideo(null);
              }}
              className="px-3 py-1 text-xs font-bold text-zinc-600 bg-zinc-100 hover:bg-zinc-200 rounded-lg cursor-pointer"
            >
              Tutup
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1">
                <label className="text-xs font-bold text-zinc-700">Judul Video *</label>
                <input
                  type="text"
                  required
                  placeholder="Judul tayangan video YouTube..."
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-bold text-zinc-700">Link YouTube / Embed URL *</label>
                <input
                  type="text"
                  required
                  placeholder="https://www.youtube.com/watch?v=..."
                  value={youtubeUrl}
                  onChange={(e) => setYoutubeUrl(e.target.value)}
                  className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                />
              </div>
            </div>

            {/* Kategori, Sub-Kategori & Durasi Dropdown */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-1">
                <label className="text-xs font-bold text-zinc-700">Kategori Utama *</label>
                <select
                  value={category}
                  onChange={(e) => {
                    const newCat = e.target.value;
                    setCategory(newCat);
                    const subList = STUDIO_CATEGORY_MAP[newCat] || [];
                    setSubCategory(subList[0] || newCat);
                  }}
                  className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-bold"
                >
                  {Object.keys(STUDIO_CATEGORY_MAP).map((catKey) => (
                    <option key={catKey} value={catKey}>
                      {catKey}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-bold text-zinc-700">Sub Kategori *</label>
                <select
                  value={subCategory}
                  onChange={(e) => setSubCategory(e.target.value)}
                  className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                >
                  {(STUDIO_CATEGORY_MAP[category] || [category]).map((sub) => (
                    <option key={sub} value={sub}>
                      {sub}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-bold text-zinc-700">Durasi Video (mm:ss)</label>
                <input
                  type="text"
                  placeholder="Contoh: 04:15"
                  value={duration}
                  onChange={(e) => setDuration(e.target.value)}
                  className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-mono"
                />
              </div>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-bold text-zinc-700">Deskripsi Singkat Video</label>
              <textarea
                rows={3}
                placeholder="Rincian / narasi tentang tayangan video..."
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => {
                  setShowAddModal(false);
                  setEditingVideo(null);
                }}
                className="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 bg-zinc-100 hover:bg-zinc-200 transition cursor-pointer"
              >
                Batal
              </button>
              <button
                type="submit"
                className="px-5 py-2 rounded-xl text-xs font-bold text-white bg-red-600 hover:bg-red-700 shadow-2xs transition cursor-pointer"
              >
                {editingVideo ? 'Simpan Perubahan' : 'Simpan Video'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Videos List Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredItems.map((item) => {
          const embedUrl = extractYoutubeEmbed(item.youtubeUrl);
          const isPlaying = activePlayId === item.id;

          return (
            <div
              key={item.id}
              className="bg-white border border-zinc-200 hover:border-zinc-300 rounded-2xl overflow-hidden shadow-2xs transition flex flex-col justify-between"
            >
              <div>
                <div className="relative aspect-video bg-black overflow-hidden flex items-center justify-center">
                  {isPlaying ? (
                    <iframe
                      src={`${embedUrl}?autoplay=1`}
                      title={item.title}
                      className="w-full h-full border-0"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      allowFullScreen
                    />
                  ) : (
                    <div
                      onClick={() => setActivePlayId(item.id)}
                      className="relative w-full h-full cursor-pointer group flex items-center justify-center bg-zinc-900"
                    >
                      <img
                        src={`https://img.youtube.com/vi/${item.youtubeId || 'dQw4w9WgXcQ'}/hqdefault.jpg`}
                        alt={item.title}
                        className="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition duration-300"
                      />
                      <div className="absolute w-12 h-12 rounded-full bg-red-600 text-white flex items-center justify-center shadow-lg group-hover:scale-110 transition">
                        <Play className="w-5 h-5 fill-current ml-0.5" />
                      </div>
                      <span className="absolute bottom-2 right-2 bg-black/80 text-white text-[10px] font-bold px-1.5 py-0.5 rounded font-mono">
                        {item.duration || '04:15'}
                      </span>
                      <span className="absolute top-2 left-2 bg-red-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded">
                        {item.category}
                      </span>
                    </div>
                  )}
                </div>

                <div className="p-4 space-y-2">
                  <h4 className="text-sm font-bold text-zinc-900 line-clamp-2 leading-snug">
                    {item.title}
                  </h4>
                  {item.description && (
                    <p className="text-xs text-zinc-500 line-clamp-2">{item.description}</p>
                  )}
                  <div className="text-[11px] text-zinc-400 flex items-center justify-between font-medium">
                    <span>Oleh: {item.author}</span>
                    <span>{item.date}</span>
                  </div>
                </div>
              </div>

              <div className="p-3 bg-zinc-50 border-t border-zinc-100 flex items-center justify-between gap-2">
                <a
                  href={item.youtubeUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="text-xs font-bold text-red-600 hover:text-red-700 flex items-center gap-1 truncate"
                >
                  <ExternalLink className="w-3.5 h-3.5 shrink-0" /> <span className="truncate">Buka di YouTube</span>
                </a>

                {(currentUser.role === 'admin' || currentUser.email === item.authorEmail) && (
                  <div className="flex items-center gap-1.5 shrink-0">
                    <button
                      onClick={() => handleEditClick(item)}
                      className="px-2.5 py-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-bold text-xs flex items-center gap-1 transition cursor-pointer"
                      title="Edit Video"
                    >
                      <Pencil className="w-3.5 h-3.5 text-zinc-600" />
                      <span>Edit</span>
                    </button>

                    <button
                      onClick={() => onDeleteVideo(item.id)}
                      className="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition cursor-pointer"
                      title="Hapus Video"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
