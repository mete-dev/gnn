import React, { useState } from 'react';
import {
  Plus,
  Trash2,
  ChevronUp,
  ChevronDown,
  Copy,
  Download,
  Code,
  Eye,
  Monitor,
  Tablet,
  Smartphone,
  ExternalLink,
  Sparkles,
  Type,
  Image as ImageIcon,
  Video,
  Quote,
  LayoutGrid,
  MousePointerClick,
  FileText,
  Send,
  Check,
  Globe,
  Settings,
  Palette,
  AlignLeft,
  AlignCenter,
  AlignRight,
  Maximize2
} from 'lucide-react';
import { Article } from '../../types/studio';

export type BlockType =
  | 'hero'
  | 'articles-grid'
  | 'video'
  | 'link-buttons'
  | 'quote'
  | 'wysiwyg-text'
  | 'cta-banner'
  | 'footer-social';

export interface LinkButtonConfig {
  id: string;
  label: string;
  url: string;
  variant: 'primary' | 'secondary' | 'emerald' | 'gradient' | 'outline' | 'dark';
  icon: 'external' | 'arrow' | 'download' | 'send' | 'star' | 'none';
  openNewTab: boolean;
  size: 'sm' | 'md' | 'lg';
}

export interface PageBlock {
  id: string;
  type: BlockType;
  title: string;
  subtitle?: string;
  content?: string;
  imageUrl?: string;
  bgType?: 'white' | 'zinc-900' | 'red-900' | 'gradient-red' | 'gradient-dark' | 'emerald-900';
  textAlign?: 'left' | 'center' | 'right';
  paddingY?: 'py-4' | 'py-8' | 'py-12' | 'py-16';
  columns?: 1 | 2 | 3 | 4;
  videoUrl?: string;
  quoteAuthor?: string;
  quoteRole?: string;
  buttons: LinkButtonConfig[];
}

interface WysiwydPageBuilderProps {
  articles?: Article[];
}

export const WysiwydPageBuilder: React.FC<WysiwydPageBuilderProps> = ({ articles = [] }) => {
  const [pageTitle, setPageTitle] = useState<string>('Halaman Liputan Khusus GNN');
  const [pageSlug, setPageSlug] = useState<string>('liputan-khusus');
  const [deviceMode, setDeviceMode] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const [activeBlockId, setActiveBlockId] = useState<string | null>('block-1');
  const [copiedCode, setCopiedCode] = useState<boolean>(false);
  const [savedSuccessMsg, setSavedSuccessMsg] = useState<string | null>(null);

  // Initial Blocks Sample Data
  const [blocks, setBlocks] = useState<PageBlock[]>([
    {
      id: 'block-1',
      type: 'hero',
      title: 'Kabar Baik Nusantara: Terobosan Inovasi Anak Bangsa 2026',
      subtitle: 'Portal visual khusus mengulas mahakarya, teknologi ramah lingkungan, dan kebudayaan Indonesia yang menginspirasi dunia.',
      imageUrl: 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1200&q=80',
      bgType: 'gradient-dark',
      textAlign: 'center',
      paddingY: 'py-16',
      buttons: [
        {
          id: 'btn-1',
          label: 'Jelajah Liputan Khusus',
          url: 'https://www.goodnewsnusantara.my.id',
          variant: 'primary',
          icon: 'arrow',
          openNewTab: true,
          size: 'lg'
        },
        {
          id: 'btn-2',
          label: 'Gabung Sahabat GNN',
          url: 'https://www.goodnewsnusantara.my.id/#/register-sahabat',
          variant: 'outline',
          icon: 'star',
          openNewTab: false,
          size: 'lg'
        }
      ]
    },
    {
      id: 'block-2',
      type: 'articles-grid',
      title: 'Sorotan Utama & Headline Terkini',
      subtitle: 'Kumpulan ulasan berita pilihan redaksi Good News Nusantara',
      bgType: 'white',
      columns: 3,
      paddingY: 'py-12',
      buttons: [
        {
          id: 'btn-grid-1',
          label: 'Lihat Semua Artikel',
          url: 'https://www.goodnewsnusantara.my.id/#/berita',
          variant: 'secondary',
          icon: 'external',
          openNewTab: false,
          size: 'md'
        }
      ]
    },
    {
      id: 'block-3',
      type: 'video',
      title: 'Dokumenter Eksklusif GNN Studio',
      subtitle: 'Menyaksikan langsung dedikasi inovator nusantara di lapangan',
      videoUrl: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
      content: 'Inovasi baterai berbasis selulosa bambu alami yang mampu menyimpan daya listrik energi terbarukan 3x lebih efisien.',
      bgType: 'zinc-900',
      textAlign: 'center',
      paddingY: 'py-12',
      buttons: [
        {
          id: 'btn-vid-1',
          label: 'Tonton di YouTube',
          url: 'https://www.youtube.com',
          variant: 'gradient',
          icon: 'external',
          openNewTab: true,
          size: 'md'
        }
      ]
    },
    {
      id: 'block-4',
      type: 'link-buttons',
      title: 'Tautan Cepat & Sumber Informasi Resmi',
      subtitle: 'Akses langsung ke kanal resmi dan dokumen publik',
      bgType: 'white',
      textAlign: 'center',
      paddingY: 'py-8',
      buttons: [
        {
          id: 'btn-link-1',
          label: 'Unduh E-Book Laporan Tahunan GNN',
          url: '#',
          variant: 'emerald',
          icon: 'download',
          openNewTab: true,
          size: 'md'
        },
        {
          id: 'btn-link-2',
          label: 'Kanal WhatsApp Resmi GNN',
          url: 'https://wa.me/',
          variant: 'primary',
          icon: 'send',
          openNewTab: true,
          size: 'md'
        },
        {
          id: 'btn-link-3',
          label: 'Panduan Penulisan Sahabat GNN',
          url: '#',
          variant: 'dark',
          icon: 'external',
          openNewTab: false,
          size: 'md'
        }
      ]
    },
    {
      id: 'block-5',
      type: 'quote',
      title: 'Catatan Redaktur Pelaksana',
      content: 'Good News Nusantara hadir untuk menyuarakan optimisme. Di balik setiap tantangan, selalu ada kisah keberanian anak bangsa yang patut diapresiasi oleh publik dunia.',
      quoteAuthor: 'Bagus Wicaksono',
      quoteRole: 'Redaktur Pelaksana GNN',
      bgType: 'emerald-900',
      textAlign: 'center',
      paddingY: 'py-12',
      buttons: [
        {
          id: 'btn-q-1',
          label: 'Baca Profil Redaksi',
          url: '#',
          variant: 'outline',
          icon: 'external',
          openNewTab: false,
          size: 'sm'
        }
      ]
    }
  ]);

  // Block creation helper
  const addBlock = (type: BlockType) => {
    const newId = 'block-' + Date.now();
    let newBlock: PageBlock = {
      id: newId,
      type,
      title: 'Judul Blok Baru',
      subtitle: 'Deskripsi ringkas blok visual Halaman Anda.',
      bgType: 'white',
      textAlign: 'left',
      paddingY: 'py-8',
      buttons: []
    };

    if (type === 'hero') {
      newBlock.title = 'Headline Utama Halaman';
      newBlock.subtitle = 'Sub-judul memikat untuk mengaitkan pembaca pada isu utama.';
      newBlock.bgType = 'gradient-dark';
      newBlock.textAlign = 'center';
      newBlock.imageUrl = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80';
      newBlock.buttons = [
        {
          id: 'btn-' + Date.now(),
          label: 'Klik Di Sini',
          url: 'https://www.goodnewsnusantara.my.id',
          variant: 'primary',
          icon: 'arrow',
          openNewTab: true,
          size: 'md'
        }
      ];
    } else if (type === 'link-buttons') {
      newBlock.title = 'Kumpulan Tautan Tautan Pilihan';
      newBlock.subtitle = 'Klik tombol di bawah ini untuk mengakses tautan lengkap';
      newBlock.textAlign = 'center';
      newBlock.buttons = [
        {
          id: 'btn-1-' + Date.now(),
          label: 'Tautan Utama 1',
          url: 'https://www.goodnewsnusantara.my.id',
          variant: 'primary',
          icon: 'external',
          openNewTab: true,
          size: 'md'
        },
        {
          id: 'btn-2-' + Date.now(),
          label: 'Tautan Pendukung 2',
          url: 'https://www.goodnewsnusantara.my.id',
          variant: 'outline',
          icon: 'none',
          openNewTab: false,
          size: 'md'
        }
      ];
    } else if (type === 'articles-grid') {
      newBlock.title = 'Daftar Artikel & Liputan Terkait';
      newBlock.columns = 3;
    } else if (type === 'video') {
      newBlock.title = 'Pemutaran Video Liputan';
      newBlock.videoUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
      newBlock.bgType = 'zinc-900';
    } else if (type === 'quote') {
      newBlock.title = 'Kutipan Pilihan';
      newBlock.content = 'Tuliskan kutipan inspiratif atau pesan penting narasumber di sini.';
      newBlock.quoteAuthor = 'Nama Narasumber';
      newBlock.quoteRole = 'Jabatan / Profesi';
      newBlock.bgType = 'gradient-red';
    } else if (type === 'cta-banner') {
      newBlock.title = 'Bergabunglah Bersama Sahabat GNN!';
      newBlock.subtitle = 'Tulis kisah inspiratif dari daerahmu dan publish di portal Good News Nusantara.';
      newBlock.bgType = 'gradient-red';
      newBlock.textAlign = 'center';
      newBlock.buttons = [
        {
          id: 'btn-cta-' + Date.now(),
          label: 'Daftar Akun Sahabat Penulis',
          url: 'https://www.goodnewsnusantara.my.id/#/register-sahabat',
          variant: 'emerald',
          icon: 'send',
          openNewTab: true,
          size: 'lg'
        }
      ];
    } else if (type === 'wysiwyg-text') {
      newBlock.title = 'Artikel & Ulasan Komprehensif';
      newBlock.content = '<p>Tulis paragraf narasi lengkap di sini. Anda dapat memasukkan teks cetak tebal, miring, dan hyperlink.</p>';
    }

    setBlocks([...blocks, newBlock]);
    setActiveBlockId(newId);
  };

  const moveBlock = (index: number, direction: 'up' | 'down') => {
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= blocks.length) return;
    const updated = [...blocks];
    const [moved] = updated.splice(index, 1);
    updated.splice(newIndex, 0, moved);
    setBlocks(updated);
  };

  const deleteBlock = (id: string) => {
    if (blocks.length <= 1) {
      alert('Halaman minimal harus memiliki 1 blok visual!');
      return;
    }
    setBlocks(blocks.filter((b) => b.id !== id));
    if (activeBlockId === id) {
      setActiveBlockId(blocks.find((b) => b.id !== id)?.id || null);
    }
  };

  const duplicateBlock = (block: PageBlock) => {
    const dup: PageBlock = {
      ...block,
      id: 'block-' + Date.now(),
      buttons: block.buttons.map((btn) => ({ ...btn, id: 'btn-' + Math.random().toString(36).slice(2, 9) }))
    };
    const idx = blocks.findIndex((b) => b.id === block.id);
    const updated = [...blocks];
    updated.splice(idx + 1, 0, dup);
    setBlocks(updated);
    setActiveBlockId(dup.id);
  };

  const updateActiveBlock = (fields: Partial<PageBlock>) => {
    if (!activeBlockId) return;
    setBlocks(blocks.map((b) => (b.id === activeBlockId ? { ...b, ...fields } : b)));
  };

  const addButtonToActiveBlock = () => {
    if (!activeBlockId) return;
    const newBtn: LinkButtonConfig = {
      id: 'btn-' + Date.now(),
      label: 'Tombol Tautan Baru',
      url: 'https://',
      variant: 'primary',
      icon: 'external',
      openNewTab: true,
      size: 'md'
    };
    setBlocks(
      blocks.map((b) => {
        if (b.id === activeBlockId) {
          return { ...b, buttons: [...b.buttons, newBtn] };
        }
        return b;
      })
    );
  };

  const updateButtonInActiveBlock = (btnId: string, fields: Partial<LinkButtonConfig>) => {
    if (!activeBlockId) return;
    setBlocks(
      blocks.map((b) => {
        if (b.id === activeBlockId) {
          return {
            ...b,
            buttons: b.buttons.map((btn) => (btn.id === btnId ? { ...btn, ...fields } : btn))
          };
        }
        return b;
      })
    );
  };

  const deleteButtonFromActiveBlock = (btnId: string) => {
    if (!activeBlockId) return;
    setBlocks(
      blocks.map((b) => {
        if (b.id === activeBlockId) {
          return {
            ...b,
            buttons: b.buttons.filter((btn) => btn.id !== btnId)
          };
        }
        return b;
      })
    );
  };

  const activeBlock = blocks.find((b) => b.id === activeBlockId);

  // HTML Export Generator
  const generateExportHtml = () => {
    return `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${pageTitle} - Good News Nusantara</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Outfit', sans-serif; }
  </style>
</head>
<body class="bg-zinc-50 text-zinc-900 antialiased min-h-screen">
  <!-- Header Navbar GNN -->
  <header class="bg-white border-b border-zinc-200 sticky top-0 z-50 py-3 px-6 flex items-center justify-between shadow-xs">
    <div class="flex items-center gap-2">
      <span class="bg-red-600 text-white px-2.5 py-1 rounded-lg text-xs font-black tracking-wider uppercase">GNN</span>
      <span class="font-extrabold text-sm text-zinc-900">Good News Nusantara</span>
    </div>
    <a href="https://www.goodnewsnusantara.my.id" class="text-xs font-bold text-red-600 hover:underline">Kembali ke Portal Utama &rarr;</a>
  </header>

  <main>
    ${blocks
      .map((b) => {
        const bgClass =
          b.bgType === 'zinc-900'
            ? 'bg-zinc-900 text-white'
            : b.bgType === 'red-900'
            ? 'bg-red-950 text-white'
            : b.bgType === 'gradient-red'
            ? 'bg-gradient-to-br from-red-700 via-red-800 to-zinc-950 text-white'
            : b.bgType === 'gradient-dark'
            ? 'bg-gradient-to-br from-zinc-900 via-zinc-950 to-red-950 text-white'
            : b.bgType === 'emerald-900'
            ? 'bg-emerald-950 text-white'
            : 'bg-white text-zinc-900';

        const alignClass =
          b.textAlign === 'center' ? 'text-center items-center' : b.textAlign === 'right' ? 'text-right items-end' : 'text-left items-start';

        const renderButtonsHtml = b.buttons
          .map((btn) => {
            const btnStyle =
              btn.variant === 'primary'
                ? 'bg-red-600 hover:bg-red-700 text-white shadow-md'
                : btn.variant === 'secondary'
                ? 'bg-zinc-900 hover:bg-zinc-800 text-white shadow-sm'
                : btn.variant === 'emerald'
                ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-md'
                : btn.variant === 'gradient'
                ? 'bg-gradient-to-r from-red-600 to-amber-600 text-white shadow-lg'
                : btn.variant === 'outline'
                ? 'bg-transparent border-2 border-current hover:bg-white/10 text-current'
                : 'bg-zinc-800 hover:bg-zinc-700 text-white';

            return `<a href="${btn.url}" ${btn.openNewTab ? 'target="_blank" rel="noopener noreferrer"' : ''} class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-extrabold text-sm transition cursor-pointer ${btnStyle}">${btn.label}</a>`;
          })
          .join('\n');

        return `<section class="${bgClass} ${b.paddingY || 'py-12'} px-6 border-b border-zinc-200/20">
      <div class="max-w-5xl mx-auto flex flex-col ${alignClass} space-y-4">
        ${b.title ? `<h2 class="text-3xl sm:text-4xl font-black leading-tight">${b.title}</h2>` : ''}
        ${b.subtitle ? `<p class="text-base sm:text-lg opacity-80 max-w-2xl">${b.subtitle}</p>` : ''}
        ${b.content ? `<div class="prose max-w-none text-base opacity-90">${b.content}</div>` : ''}
        ${
          b.imageUrl
            ? `<img src="${b.imageUrl}" alt="${b.title}" class="w-full max-h-[480px] object-cover rounded-2xl shadow-xl my-4" />`
            : ''
        }
        ${
          b.buttons.length > 0
            ? `<div class="flex flex-wrap gap-3 pt-4 ${b.textAlign === 'center' ? 'justify-center' : b.textAlign === 'right' ? 'justify-end' : 'justify-start'}">${renderButtonsHtml}</div>`
            : ''
        }
      </div>
    </section>`;
      })
      .join('\n\n')}
  </main>

  <footer class="bg-zinc-950 text-zinc-400 text-center py-8 px-4 text-xs">
    <p>&copy; 2026 Good News Nusantara. Hak Cipta Dilindungi Undang-Undang.</p>
  </footer>
</body>
</html>`;
  };

  const handleCopyCode = () => {
    const html = generateExportHtml();
    navigator.clipboard.writeText(html);
    setCopiedCode(true);
    setTimeout(() => setCopiedCode(false), 3000);
  };

  const handleDownloadHtml = () => {
    const html = generateExportHtml();
    const blob = new Blob([html], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${pageSlug || 'halaman-gnn'}.html`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const handleSavePage = () => {
    setSavedSuccessMsg('✓ Halaman web WYSIWYD berhasil disimpan!');
    setTimeout(() => setSavedSuccessMsg(null), 4000);
  };

  return (
    <div className="space-y-6">
      {/* Header Controls Bar */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center font-bold">
            <LayoutGrid className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="text-[11px] font-extrabold uppercase tracking-wider bg-red-100 text-red-700 px-2 py-0.5 rounded-md">
                WYSIWYD Builder
              </span>
              <span className="text-xs font-bold text-zinc-400">/studio/halaman</span>
            </div>
            <h1 className="text-lg font-black text-zinc-900">Perancang Halaman Web Visual</h1>
          </div>
        </div>

        {/* Action Controls */}
        <div className="flex flex-wrap items-center gap-2">
          {/* Device Preview Switches */}
          <div className="bg-zinc-100 p-1 rounded-xl flex items-center gap-1 border border-zinc-200">
            <button
              type="button"
              onClick={() => setDeviceMode('desktop')}
              title="Pratinjau Desktop"
              className={`p-2 rounded-lg transition cursor-pointer ${
                deviceMode === 'desktop' ? 'bg-white text-red-600 shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-900'
              }`}
            >
              <Monitor className="w-4 h-4" />
            </button>
            <button
              type="button"
              onClick={() => setDeviceMode('tablet')}
              title="Pratinjau Tablet"
              className={`p-2 rounded-lg transition cursor-pointer ${
                deviceMode === 'tablet' ? 'bg-white text-red-600 shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-900'
              }`}
            >
              <Tablet className="w-4 h-4" />
            </button>
            <button
              type="button"
              onClick={() => setDeviceMode('mobile')}
              title="Pratinjau Mobile"
              className={`p-2 rounded-lg transition cursor-pointer ${
                deviceMode === 'mobile' ? 'bg-white text-red-600 shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-900'
              }`}
            >
              <Smartphone className="w-4 h-4" />
            </button>
          </div>

          <button
            type="button"
            onClick={handleCopyCode}
            className="px-3 py-2 rounded-xl border border-zinc-300 bg-white hover:bg-zinc-50 text-zinc-700 font-extrabold text-xs flex items-center gap-1.5 transition cursor-pointer"
          >
            {copiedCode ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4 text-zinc-500" />}
            {copiedCode ? 'Tersalin!' : 'Salin Kode HTML'}
          </button>

          <button
            type="button"
            onClick={handleDownloadHtml}
            className="px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-extrabold text-xs flex items-center gap-1.5 shadow-sm transition cursor-pointer"
          >
            <Download className="w-4 h-4 text-red-400" /> Unduh HTML
          </button>

          <button
            type="button"
            onClick={handleSavePage}
            className="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-extrabold text-xs flex items-center gap-1.5 shadow-md transition cursor-pointer"
          >
            <Check className="w-4 h-4" /> Simpan Halaman
          </button>
        </div>
      </div>

      {savedSuccessMsg && (
        <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-extrabold flex items-center gap-2">
          <Check className="w-4 h-4 text-emerald-600" /> {savedSuccessMsg}
        </div>
      )}

      {/* Main Builder Grid: Canvas & Inspector */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* Left Side: Add Block Toolbar & Page Canvas */}
        <div className="lg:col-span-8 space-y-6">
          {/* Add Block Palette */}
          <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-xs space-y-3">
            <h3 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider flex items-center gap-1.5">
              <Plus className="w-4 h-4 text-red-600" /> Tambah Blok Visual Halaman
            </h3>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button
                type="button"
                onClick={() => addBlock('hero')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <Sparkles className="w-4 h-4 text-red-600 shrink-0 group-hover:scale-110 transition" />
                <span>Hero Banner</span>
              </button>

              <button
                type="button"
                onClick={() => addBlock('link-buttons')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <MousePointerClick className="w-4 h-4 text-blue-600 shrink-0 group-hover:scale-110 transition" />
                <span>Tombol Tautan Link</span>
              </button>

              <button
                type="button"
                onClick={() => addBlock('articles-grid')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <LayoutGrid className="w-4 h-4 text-emerald-600 shrink-0 group-hover:scale-110 transition" />
                <span>Grid Berita</span>
              </button>

              <button
                type="button"
                onClick={() => addBlock('video')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <Video className="w-4 h-4 text-purple-600 shrink-0 group-hover:scale-110 transition" />
                <span>Video Player</span>
              </button>

              <button
                type="button"
                onClick={() => addBlock('quote')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <Quote className="w-4 h-4 text-amber-600 shrink-0 group-hover:scale-110 transition" />
                <span>Kutipan Quote</span>
              </button>

              <button
                type="button"
                onClick={() => addBlock('cta-banner')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <Send className="w-4 h-4 text-red-600 shrink-0 group-hover:scale-110 transition" />
                <span>Banner CTA</span>
              </button>

              <button
                type="button"
                onClick={() => addBlock('wysiwyg-text')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <FileText className="w-4 h-4 text-teal-600 shrink-0 group-hover:scale-110 transition" />
                <span>Text WYSIWYG</span>
              </button>

              <button
                type="button"
                onClick={() => addBlock('footer-social')}
                className="p-3 rounded-xl border border-zinc-200 hover:border-red-600 hover:bg-red-50/40 text-left transition flex items-center gap-2 text-xs font-bold text-zinc-800 group cursor-pointer"
              >
                <Globe className="w-4 h-4 text-blue-500 shrink-0 group-hover:scale-110 transition" />
                <span>Footer Sosmed</span>
              </button>
            </div>
          </div>

          {/* Canvas Wrapper (What You See Is What You Design) */}
          <div className="flex justify-center bg-zinc-200/70 p-4 sm:p-6 rounded-3xl border border-zinc-300 overflow-x-auto min-h-[600px]">
            <div
              className={`bg-white rounded-2xl shadow-xl transition-all duration-300 overflow-hidden ${
                deviceMode === 'desktop'
                  ? 'w-full max-w-4xl'
                  : deviceMode === 'tablet'
                  ? 'w-[768px]'
                  : 'w-[375px]'
              }`}
            >
              {/* Fake Browser Header Bar */}
              <div className="bg-zinc-900 text-zinc-300 px-4 py-2.5 flex items-center justify-between text-xs border-b border-zinc-800">
                <div className="flex items-center gap-1.5">
                  <span className="w-2.5 h-2.5 rounded-full bg-red-500 inline-block" />
                  <span className="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block" />
                  <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block" />
                  <span className="ml-2 font-mono text-[11px] text-zinc-400">
                    https://www.goodnewsnusantara.my.id/studio/halaman/{pageSlug}
                  </span>
                </div>
                <span className="text-[10px] uppercase font-bold text-zinc-500 tracking-wider">
                  Live Canvas Preview
                </span>
              </div>

              {/* Render Blocks */}
              <div className="divide-y divide-zinc-100">
                {blocks.map((block, idx) => {
                  const isActive = activeBlockId === block.id;

                  const bgStyle =
                    block.bgType === 'zinc-900'
                      ? 'bg-zinc-900 text-white'
                      : block.bgType === 'red-900'
                      ? 'bg-red-950 text-white'
                      : block.bgType === 'gradient-red'
                      ? 'bg-gradient-to-br from-red-700 via-red-800 to-zinc-950 text-white'
                      : block.bgType === 'gradient-dark'
                      ? 'bg-gradient-to-br from-zinc-900 via-zinc-950 to-red-950 text-white'
                      : block.bgType === 'emerald-900'
                      ? 'bg-emerald-950 text-white'
                      : 'bg-white text-zinc-900';

                  const alignStyle =
                    block.textAlign === 'center'
                      ? 'text-center items-center'
                      : block.textAlign === 'right'
                      ? 'text-right items-end'
                      : 'text-left items-start';

                  return (
                    <div
                      key={block.id}
                      onClick={() => setActiveBlockId(block.id)}
                      className={`relative group cursor-pointer transition-all ${
                        isActive ? 'ring-4 ring-red-600 ring-offset-2 z-10' : 'hover:ring-2 hover:ring-red-400/50'
                      }`}
                    >
                      {/* Block Hover/Active Floating Ribbon Controls */}
                      <div className="absolute top-3 right-3 z-20 opacity-0 group-hover:opacity-100 transition flex items-center gap-1 bg-zinc-900/90 backdrop-blur-xs p-1.5 rounded-xl shadow-lg text-white text-xs">
                        <span className="text-[10px] font-extrabold uppercase px-2 text-zinc-400">
                          {block.type}
                        </span>
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            moveBlock(idx, 'up');
                          }}
                          disabled={idx === 0}
                          className="p-1 hover:bg-zinc-800 rounded-lg text-zinc-300 disabled:opacity-30"
                          title="Pindah ke Atas"
                        >
                          <ChevronUp className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            moveBlock(idx, 'down');
                          }}
                          disabled={idx === blocks.length - 1}
                          className="p-1 hover:bg-zinc-800 rounded-lg text-zinc-300 disabled:opacity-30"
                          title="Pindah ke Bawah"
                        >
                          <ChevronDown className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            duplicateBlock(block);
                          }}
                          className="p-1 hover:bg-zinc-800 rounded-lg text-zinc-300"
                          title="Duplikasi Blok"
                        >
                          <Copy className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            deleteBlock(block.id);
                          }}
                          className="p-1 hover:bg-red-950 rounded-lg text-red-400"
                          title="Hapus Blok"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>

                      {/* Render Actual Block Visual Content */}
                      <div className={`${bgStyle} ${block.paddingY || 'py-12'} px-6 sm:px-10`}>
                        <div className={`max-w-4xl mx-auto flex flex-col ${alignStyle} space-y-4`}>
                          {block.title && (
                            <h2 className="text-2xl sm:text-3xl font-black leading-tight tracking-tight">
                              {block.title}
                            </h2>
                          )}

                          {block.subtitle && (
                            <p className="text-sm sm:text-base opacity-80 max-w-2xl font-normal leading-relaxed">
                              {block.subtitle}
                            </p>
                          )}

                          {block.type === 'hero' && block.imageUrl && (
                            <div className="w-full my-4 rounded-2xl overflow-hidden shadow-xl border border-white/10">
                              <img
                                src={block.imageUrl}
                                alt={block.title}
                                className="w-full h-64 sm:h-80 object-cover"
                              />
                            </div>
                          )}

                          {block.type === 'quote' && (
                            <div className="bg-white/10 backdrop-blur-xs p-6 rounded-2xl border border-white/20 my-2 text-center max-w-2xl">
                              <Quote className="w-8 h-8 text-amber-400 mx-auto mb-3 opacity-90" />
                              <p className="text-base sm:text-lg italic font-medium">"{block.content}"</p>
                              <div className="mt-4 text-xs font-bold opacity-90">
                                <span>{block.quoteAuthor}</span> —{' '}
                                <span className="text-amber-300">{block.quoteRole}</span>
                              </div>
                            </div>
                          )}

                          {block.type === 'articles-grid' && (
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 w-full my-4 text-left">
                              {[1, 2, 3].map((n) => (
                                <div
                                  key={n}
                                  className="bg-zinc-50 rounded-xl p-3 border border-zinc-200 space-y-2 shadow-2xs"
                                >
                                  <div className="h-28 bg-zinc-200 rounded-lg overflow-hidden relative">
                                    <img
                                      src={`https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=400&q=80`}
                                      alt="Berita"
                                      className="w-full h-full object-cover"
                                    />
                                    <span className="absolute top-2 left-2 bg-red-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded">
                                      NASIONAL
                                    </span>
                                  </div>
                                  <p className="font-extrabold text-xs text-zinc-900 line-clamp-2">
                                    Terobosan Teknologi Indonesia yang Diapresiasi Dunia Internasional #{n}
                                  </p>
                                  <p className="text-[11px] text-zinc-500">2 Oktober 2026</p>
                                </div>
                              ))}
                            </div>
                          )}

                          {block.type === 'wysiwyg-text' && block.content && (
                            <div
                              className="prose max-w-none text-sm leading-relaxed"
                              dangerouslySetInnerHTML={{ __html: block.content }}
                            />
                          )}

                          {/* Link Buttons Render */}
                          {block.buttons && block.buttons.length > 0 && (
                            <div
                              className={`flex flex-wrap gap-2.5 pt-3 w-full ${
                                block.textAlign === 'center'
                                  ? 'justify-center'
                                  : block.textAlign === 'right'
                                  ? 'justify-end'
                                  : 'justify-start'
                              }`}
                            >
                              {block.buttons.map((btn) => {
                                const btnStyle =
                                  btn.variant === 'primary'
                                    ? 'bg-red-600 text-white shadow-md hover:bg-red-700'
                                    : btn.variant === 'secondary'
                                    ? 'bg-zinc-900 text-white shadow-sm hover:bg-zinc-800'
                                    : btn.variant === 'emerald'
                                    ? 'bg-emerald-600 text-white shadow-md hover:bg-emerald-700'
                                    : btn.variant === 'gradient'
                                    ? 'bg-gradient-to-r from-red-600 to-amber-600 text-white shadow-lg'
                                    : btn.variant === 'outline'
                                    ? 'bg-transparent border-2 border-current text-current hover:bg-white/10'
                                    : 'bg-zinc-800 text-white hover:bg-zinc-700';

                                const sizeStyle =
                                  btn.size === 'sm'
                                    ? 'px-3 py-1.5 text-xs'
                                    : btn.size === 'lg'
                                    ? 'px-6 py-3 text-sm'
                                    : 'px-4 py-2 text-xs';

                                return (
                                  <a
                                    key={btn.id}
                                    href={btn.url}
                                    target={btn.openNewTab ? '_blank' : '_self'}
                                    rel="noreferrer"
                                    onClick={(e) => e.preventDefault()}
                                    className={`inline-flex items-center gap-1.5 rounded-xl font-extrabold transition cursor-pointer ${btnStyle} ${sizeStyle}`}
                                  >
                                    <span>{btn.label}</span>
                                    {btn.icon === 'external' && <ExternalLink className="w-3.5 h-3.5" />}
                                    {btn.icon === 'send' && <Send className="w-3.5 h-3.5" />}
                                    {btn.icon === 'download' && <Download className="w-3.5 h-3.5" />}
                                  </a>
                                );
                              })}
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </div>

        {/* Right Side: Real-Time Block Inspector Panel */}
        <div className="lg:col-span-4 bg-white border border-zinc-200 rounded-2xl p-5 shadow-xs space-y-6 sticky top-24">
          <div className="flex items-center justify-between border-b border-zinc-200 pb-3">
            <h3 className="text-sm font-extrabold text-zinc-900 flex items-center gap-2">
              <Settings className="w-4 h-4 text-red-600" /> Inspek & Edit Blok Tautan
            </h3>
            {activeBlock && (
              <span className="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-700">
                {activeBlock.type}
              </span>
            )}
          </div>

          {!activeBlock ? (
            <div className="py-12 text-center text-xs text-zinc-400 font-medium">
              Klik salah satu blok pada kanvas untuk mengedit properti dan tombol tautan.
            </div>
          ) : (
            <div className="space-y-4 text-xs">
              {/* Page Slug & Meta */}
              <div className="space-y-1.5">
                <label className="font-extrabold text-zinc-700 uppercase tracking-wider block">
                  Judul Halaman Web
                </label>
                <input
                  type="text"
                  value={pageTitle}
                  onChange={(e) => setPageTitle(e.target.value)}
                  className="w-full bg-zinc-50 border border-zinc-300 rounded-xl px-3 py-2 text-xs font-bold text-zinc-900 focus:outline-none focus:border-red-600"
                />
              </div>

              {/* Block Title Input */}
              <div className="space-y-1.5">
                <label className="font-extrabold text-zinc-700 uppercase tracking-wider block">
                  Judul Blok
                </label>
                <input
                  type="text"
                  value={activeBlock.title}
                  onChange={(e) => updateActiveBlock({ title: e.target.value })}
                  className="w-full bg-zinc-50 border border-zinc-300 rounded-xl px-3 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600"
                />
              </div>

              {/* Subtitle Input */}
              <div className="space-y-1.5">
                <label className="font-extrabold text-zinc-700 uppercase tracking-wider block">
                  Sub-Judul / Deskripsi Ringkas
                </label>
                <textarea
                  rows={2}
                  value={activeBlock.subtitle || ''}
                  onChange={(e) => updateActiveBlock({ subtitle: e.target.value })}
                  className="w-full bg-zinc-50 border border-zinc-300 rounded-xl px-3 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600"
                />
              </div>

              {/* Background Style Switcher */}
              <div className="space-y-1.5">
                <label className="font-extrabold text-zinc-700 uppercase tracking-wider block flex items-center gap-1">
                  <Palette className="w-3.5 h-3.5 text-red-600" /> Gaya Warna Latar Belakang
                </label>
                <select
                  value={activeBlock.bgType || 'white'}
                  onChange={(e) => updateActiveBlock({ bgType: e.target.value as any })}
                  className="w-full bg-zinc-50 border border-zinc-300 rounded-xl px-3 py-2 text-xs font-bold text-zinc-900 focus:outline-none focus:border-red-600"
                >
                  <option value="white">Putih Bersih (White)</option>
                  <option value="zinc-900">Hitam Studio (Zinc 900)</option>
                  <option value="red-900">Merah Redaksi (Red 900)</option>
                  <option value="gradient-red">Gradien Merah Nusantara</option>
                  <option value="gradient-dark">Gradien Dark Premium</option>
                  <option value="emerald-900">Hijau Emerald (Emerald 900)</option>
                </select>
              </div>

              {/* Text Alignment */}
              <div className="space-y-1.5">
                <label className="font-extrabold text-zinc-700 uppercase tracking-wider block">
                  Rata Teks
                </label>
                <div className="grid grid-cols-3 gap-1 bg-zinc-100 p-1 rounded-xl">
                  <button
                    type="button"
                    onClick={() => updateActiveBlock({ textAlign: 'left' })}
                    className={`py-1.5 rounded-lg flex items-center justify-center transition cursor-pointer ${
                      activeBlock.textAlign === 'left' ? 'bg-white text-red-600 font-bold shadow-xs' : 'text-zinc-500'
                    }`}
                  >
                    <AlignLeft className="w-4 h-4" />
                  </button>
                  <button
                    type="button"
                    onClick={() => updateActiveBlock({ textAlign: 'center' })}
                    className={`py-1.5 rounded-lg flex items-center justify-center transition cursor-pointer ${
                      activeBlock.textAlign === 'center' ? 'bg-white text-red-600 font-bold shadow-xs' : 'text-zinc-500'
                    }`}
                  >
                    <AlignCenter className="w-4 h-4" />
                  </button>
                  <button
                    type="button"
                    onClick={() => updateActiveBlock({ textAlign: 'right' })}
                    className={`py-1.5 rounded-lg flex items-center justify-center transition cursor-pointer ${
                      activeBlock.textAlign === 'right' ? 'bg-white text-red-600 font-bold shadow-xs' : 'text-zinc-500'
                    }`}
                  >
                    <AlignRight className="w-4 h-4" />
                  </button>
                </div>
              </div>

              {/* Custom Image URL for Hero */}
              {activeBlock.type === 'hero' && (
                <div className="space-y-1.5">
                  <label className="font-extrabold text-zinc-700 uppercase tracking-wider block">
                    URL Gambar Sampul Hero
                  </label>
                  <input
                    type="url"
                    value={activeBlock.imageUrl || ''}
                    onChange={(e) => updateActiveBlock({ imageUrl: e.target.value })}
                    className="w-full bg-zinc-50 border border-zinc-300 rounded-xl px-3 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600"
                  />
                </div>
              )}

              {/* Link Buttons Inspector Section */}
              <div className="pt-3 border-t border-zinc-200 space-y-3">
                <div className="flex items-center justify-between">
                  <label className="font-extrabold text-zinc-900 uppercase tracking-wider flex items-center gap-1.5">
                    <MousePointerClick className="w-4 h-4 text-red-600" /> Tombol Tautan Link ({activeBlock.buttons.length})
                  </label>
                  <button
                    type="button"
                    onClick={addButtonToActiveBlock}
                    className="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 font-extrabold text-[11px] flex items-center gap-1 transition cursor-pointer"
                  >
                    <Plus className="w-3.5 h-3.5" /> Tambah Tombol
                  </button>
                </div>

                {activeBlock.buttons.length === 0 ? (
                  <p className="text-[11px] text-zinc-400 font-medium">Belum ada tombol tautan pada blok ini.</p>
                ) : (
                  <div className="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                    {activeBlock.buttons.map((btn, index) => (
                      <div
                        key={btn.id}
                        className="bg-zinc-50 border border-zinc-200 rounded-xl p-3 space-y-2 relative group shadow-2xs"
                      >
                        <div className="flex items-center justify-between">
                          <span className="font-black text-[10px] text-zinc-500 uppercase">
                            Tombol #{index + 1}
                          </span>
                          <button
                            type="button"
                            onClick={() => deleteButtonFromActiveBlock(btn.id)}
                            className="text-zinc-400 hover:text-red-600 text-xs font-bold"
                            title="Hapus Tombol"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>

                        {/* Label Input */}
                        <div>
                          <label className="text-[10px] font-extrabold text-zinc-600 block">Teks Label Tombol *</label>
                          <input
                            type="text"
                            value={btn.label}
                            onChange={(e) => updateButtonInActiveBlock(btn.id, { label: e.target.value })}
                            className="w-full bg-white border border-zinc-300 rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 font-bold focus:border-red-600"
                          />
                        </div>

                        {/* URL Link Input */}
                        <div>
                          <label className="text-[10px] font-extrabold text-zinc-600 block">URL Tautan Target (Link) *</label>
                          <input
                            type="text"
                            value={btn.url}
                            onChange={(e) => updateButtonInActiveBlock(btn.id, { url: e.target.value })}
                            placeholder="https://..."
                            className="w-full bg-white border border-zinc-300 rounded-lg px-2.5 py-1.5 text-xs text-zinc-900 font-medium focus:border-red-600"
                          />
                        </div>

                        {/* Variant & New Tab */}
                        <div className="grid grid-cols-2 gap-2">
                          <div>
                            <label className="text-[10px] font-extrabold text-zinc-600 block">Warna Tombol</label>
                            <select
                              value={btn.variant}
                              onChange={(e) => updateButtonInActiveBlock(btn.id, { variant: e.target.value as any })}
                              className="w-full bg-white border border-zinc-300 rounded-lg px-2 py-1 text-[11px] font-bold text-zinc-900"
                            >
                              <option value="primary">Merah GNN</option>
                              <option value="secondary">Hitam Studio</option>
                              <option value="emerald">Hijau Emerald</option>
                              <option value="gradient">Gradien Emas</option>
                              <option value="outline">Garis (Outline)</option>
                            </select>
                          </div>
                          <div>
                            <label className="text-[10px] font-extrabold text-zinc-600 block">Buka di Tab Baru?</label>
                            <select
                              value={btn.openNewTab ? 'true' : 'false'}
                              onChange={(e) => updateButtonInActiveBlock(btn.id, { openNewTab: e.target.value === 'true' })}
                              className="w-full bg-white border border-zinc-300 rounded-lg px-2 py-1 text-[11px] font-bold text-zinc-900"
                            >
                              <option value="true">Ya (_blank)</option>
                              <option value="false">Tidak (Tab Sama)</option>
                            </select>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
