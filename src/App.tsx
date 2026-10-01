import React, { useState, useEffect, useRef } from 'react';
import {
  Search,
  Flame,
  Tv,
  Radio,
  Share2,
  Bookmark,
  ThumbsUp,
  MessageSquare,
  Clock,
  ChevronRight,
  TrendingUp,
  Download,
  Send,
  Image as ImageIcon,
  Sliders,
  X,
  Play,
  Camera,
  Check,
  Menu,
  ChevronDown,
  RefreshCw,
  ExternalLink,
  Plus,
  Eye,
  EyeOff,
  Lock,
  ArrowLeft,
  ArrowRight,
  Film,
  Moon,
  Sun,
  Mail,
  AtSign,
  Twitter,
  Instagram,
  Facebook
} from 'lucide-react';
import html2canvas from 'html2canvas';
import { toPng } from 'html-to-image';
import { GnfiLogo } from './components/GnfiLogo';
import { StudioDashboard } from './components/studio/StudioDashboard';
import { SahabatRegisterModal } from './components/studio/SahabatRegisterModal';
import { User, Role, VideoItem } from './types/studio';

// Article model
export interface Article {
  id: string;
  category: 'NASIONAL' | 'INTERNASIONAL' | 'EKONOMI' | 'OLAHRAGA' | 'TEKNOLOGI' | 'HIBURAN' | 'GAYA HIDUP';
  subCategory?: string;
  title: string;
  content: string;
  image: string;
  caption?: string;
  breaking: boolean;
  featured: boolean;
  popularRank?: number;
  bullets: string[];
  date: string;
  timeAgo: string;
  author: string;
  authorEmail?: string;
  editor: string;
  location: string;
  keywords?: string[];
  imageCredit?: string;
  likes: number;
  views: number;
  comments: { id: string; user: string; text: string; time: string }[];
  isPhotoGallery?: boolean;
  photoCount?: number;
}

// Helpers for URL slug mapping
export const slugify = (text: string) => {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
};

export const getAuthorUsername = (art: Article) => {
  if (art.authorEmail) return art.authorEmail.split('@')[0].toLowerCase();
  return slugify(art.author);
};

export default function App() {
  const [studioUser, setStudioUser] = useState<{ email: string; name: string } | null>(() => {
    try {
      const saved = localStorage.getItem('studio_user');
      return saved ? JSON.parse(saved) : null;
    } catch (e) {
      return null;
    }
  });

  const [activeTab, setActiveTab] = useState<'home' | 'category' | 'detail' | 'story' | 'cms' | 'tv' | 'loginStudio' | 'author'>(() => {
    try {
      const hash = typeof window !== 'undefined' ? window.location.hash : '';
      if (hash.startsWith('#/studio')) {
        const saved = typeof window !== 'undefined' ? localStorage.getItem('studio_user') : null;
        return saved ? 'cms' : 'loginStudio';
      }
    } catch (e) {}
    return 'home';
  });

  const [selectedAuthor, setSelectedAuthor] = useState<{ name: string; username: string; email: string } | null>(null);

  const [loginEmail, setLoginEmail] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [loginError, setLoginError] = useState('');

  const [selectedCategory, setSelectedCategory] = useState<string>('SEMUA');
  const [activeTopNav, setActiveTopNav] = useState<string>('GoodTalk');
  const [isDarkMode, setIsDarkMode] = useState<boolean>(() => {
    const saved = localStorage.getItem('gnfi_theme');
    return saved ? saved === 'dark' : false;
  });
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [visibleCount, setVisibleCount] = useState<number>(12);

  // Persist dark mode and apply class on document
  useEffect(() => {
    document.title = "Good News Nusantara (GNN) - Makin Tahu Indonesia";
    if (isDarkMode) {
      document.documentElement.classList.add('dark');
      localStorage.setItem('gnfi_theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem('gnfi_theme', 'light');
    }
  }, [isDarkMode]);
  const [articles, setArticles] = useState<Article[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [detailArticle, setDetailArticle] = useState<Article | null>(null);
  const [bookmarkedIds, setBookmarkedIds] = useState<string[]>([]);
  const [trendingTopics, setTrendingTopics] = useState<string[]>([]);
  const [liveSchedule, setLiveSchedule] = useState<{ time: string; program: string; host: string; isLive: boolean }[]>([]);
  
  // Mobile drawer
  const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);
  const [searchOpen, setSearchOpen] = useState<boolean>(false);

  // Reader interactive state
  const [commentName, setCommentName] = useState<string>('');
  const [commentText, setCommentText] = useState<string>('');
  const [submittingComment, setSubmittingComment] = useState<boolean>(false);
  const [copySuccess, setCopySuccess] = useState<boolean>(false);

  // Story & Social Card Render Creator State
  const [storyArticle, setStoryArticle] = useState<Article | null>(null);
  const [storyHighlightText, setStoryHighlightText] = useState<string>('');
  const [storyMainText, setStoryMainText] = useState<string>('Sempat Menguat, Kini 1 USD Kembali ke Rp18.000');
  const [storyBrand, setStoryBrand] = useState<'gnn' | 'cnn'>('gnn');
  const [storyRatio, setStoryRatio] = useState<'4:5' | '9:16' | '1:1'>('4:5');
  const [storyCustomImage, setStoryCustomImage] = useState<string>('https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80');
  const [storyFontSize, setStoryFontSize] = useState<'sm' | 'md' | 'lg'>('md');
  const [storyLayout, setStoryLayout] = useState<'full-cover' | 'gnn-split'>('full-cover');
  const [storyCreditText, setStoryCreditText] = useState<string>('Unsplash');
  const [isExportingStory, setIsExportingStory] = useState<boolean>(false);
  const storyPreviewRef = useRef<HTMLDivElement>(null);

  // CMS & AI Generation State
  const [cmsTitle, setCmsTitle] = useState<string>('');
  const [cmsContent, setCmsContent] = useState<string>('');
  const [cmsCategory, setCmsCategory] = useState<string>('Ekonomi');
  const [cmsSubCategory, setCmsSubCategory] = useState<string>('Ekonomi');
  const [cmsImage, setCmsImage] = useState<string>('');
  const [cmsAuthor, setCmsAuthor] = useState<string>('');
  const [cmsBreaking, setCmsBreaking] = useState<boolean>(false);
  const [cmsBullets, setCmsBullets] = useState<string[]>(['', '', '', '']);
  const [aiTopic, setAiTopic] = useState<string>('');
  const [generatingAI, setGeneratingAI] = useState<boolean>(false);
  const [savingArticle, setSavingArticle] = useState<boolean>(false);
  const [showRegisterModal, setShowRegisterModal] = useState<boolean>(false);

  const [videos, setVideos] = useState<VideoItem[]>([]);
  const [activeVideo, setActiveVideo] = useState<VideoItem | null>(null);
  const [selectedVideoModal, setSelectedVideoModal] = useState<VideoItem | null>(null);

  // GNN TV Portal specific state
  const [tvCategoryFilter, setTvCategoryFilter] = useState<string>('SEMUA');
  const [tvLikedIds, setTvLikedIds] = useState<string[]>([]);
  const [tvCopiedId, setTvCopiedId] = useState<string | null>(null);
  const playerRef = useRef<HTMLDivElement>(null);

  const fetchVideos = async () => {
    try {
      const res = await fetch('/api/videos').then((r) => r.json());
      if (res.success && res.data && res.data.length > 0) {
        setVideos(res.data);
        setActiveVideo(res.data[0]);
      } else {
        const fallbackVids: VideoItem[] = [
          {
            id: 'vid-101',
            title: 'Keindahan Alam Indonesia dari Udara | Good News Nusantara',
            youtubeUrl: 'https://www.youtube.com/watch?v=w80-jEaF5o0',
            youtubeId: 'w80-jEaF5o0',
            description: 'Dokumenter pendek keindahan bentang alam nusantara Gunung Bromo, Bali, dan pantai pesisir eksotis Indonesia.',
            category: 'PARIWISATA',
            author: 'Redaktur Pelaksana (Reviuwer)',
            authorEmail: 'reviewer@gnn.id',
            date: '29 Sep 2026',
            duration: '04:15'
          },
          {
            id: 'vid-102',
            title: 'Inovasi Anak Bangsa 2026: Teknologi Hijau & Filter Air Suria',
            youtubeUrl: 'https://www.youtube.com/watch?v=5qap5aO4i9A',
            youtubeId: '5qap5aO4i9A',
            description: 'Liputan inovasi pemurni air bertenaga surya buatan mahasiswa IPB dan UGM untuk desa terpencil.',
            category: 'TEKNOLOGI',
            author: 'Admin Utama GNN',
            authorEmail: 'admin@gnn.id',
            date: '28 Sep 2026',
            duration: '06:30'
          },
          {
            id: 'vid-103',
            title: 'Swadharma Bhakti Nagara: Pertumbuhan Ekonomi & UMKM Digital',
            youtubeUrl: 'https://www.youtube.com/watch?v=EstkkvgM1KI',
            youtubeId: 'EstkkvgM1KI',
            description: 'Dokumenter perjalanan perbankan nasional dalam mendanai infrastruktur dan ekonomi kerakyatan Indonesia.',
            category: 'EKONOMI',
            author: 'Bagus Wicaksono',
            authorEmail: 'bagus@gnn.id',
            date: '28 Sep 2026',
            duration: '06:45'
          },
          {
            id: 'vid-104',
            title: 'Warisan Budaya Reog Ponorogo & Kebaya Indonesia di UNESCO Paris',
            youtubeUrl: 'https://www.youtube.com/watch?v=50F_bFuhw7Y',
            youtubeId: '50F_bFuhw7Y',
            description: 'Dokumentasi pementasan Reog Ponorogo saat disahkan UNESCO sebagai Warisan Budaya Takbenda Dunia.',
            category: 'BUDAYA',
            author: 'Fadhil Al Anshori',
            authorEmail: 'fadhil@gnn.id',
            date: '27 Sep 2026',
            duration: '05:10'
          },
          {
            id: 'vid-105',
            title: 'Konservasi Badak Jawa & Hutan Ujung Kulon Banten',
            youtubeUrl: 'https://www.youtube.com/watch?v=Kj5Q8x1oRRE',
            youtubeId: 'Kj5Q8x1oRRE',
            description: 'Upaya pelestarian satwa langka badak jawa oleh Rhino Protection Unit dan Kementerian LHK.',
            category: 'LINGKUNGAN',
            author: 'Annisa Maharani',
            authorEmail: 'annisa@gnn.id',
            date: '26 Sep 2026',
            duration: '07:20'
          },
          {
            id: 'vid-106',
            title: 'Kisah Petani Milenial Ekspor Kopi Arabika ke Pasar Eropa',
            youtubeUrl: 'https://www.youtube.com/watch?v=9v1L623m48w',
            youtubeId: '9v1L623m48w',
            description: 'Kisah sukses generasi muda Sukabumi memproduksi kopi organik dan menembus ekspor ke Eropa.',
            category: 'INSPIRASI',
            author: 'Redaksi GNN',
            authorEmail: 'admin@gnn.id',
            date: '25 Sep 2026',
            duration: '08:15'
          }
        ];
        setVideos(fallbackVids);
        setActiveVideo(fallbackVids[0]);
      }
    } catch (e) {
      console.error('Failed to load videos:', e);
    }
  };

  // Fetch initial data
  useEffect(() => {
    fetchArticles();
    fetchVideos();
  }, []);

  // Hash Route Navigation Sync for Public Portal & Studio
  // Supports:
  // - Link Penulis: /#/:username  (e.g., /#/fadhil, /#/bagus, /#/sahabat)
  // - Link Artikel: /#/:username/:judul-artikel (e.g., /#/fadhil/sempat-menguat-kini-1-usd-kembali-ke-rp18000)
  useEffect(() => {
    const handleHash = () => {
      const rawHash = window.location.hash;
      if (!rawHash || rawHash === '#/' || rawHash === '#/home') {
        setActiveTab('home');
        return;
      }

      const cleanHash = rawHash.replace(/^#\//, '');
      const parts = cleanHash.split('/');

      if (parts[0] === 'kategori' && parts[1]) {
        setSelectedCategory(decodeURIComponent(parts[1]));
        setActiveTab('category');
        return;
      }
      if (parts[0] === 'story' || parts[0] === 'render') {
        const targetId = parts[1];
        if (targetId && articles.length > 0) {
          const found = articles.find((a) => a.id === targetId || slugify(a.title) === targetId || getAuthorUsername(a) === targetId);
          if (found) {
            setStoryArticle(found);
            setStoryCustomImage(found.image);
            setStoryMainText(found.title);
            let initialCredit = found.imageCredit || '';
            if (!initialCredit && found.caption && found.caption.toLowerCase().includes('unsplash')) {
              initialCredit = 'Unsplash';
            }
            setStoryCreditText(initialCredit || found.author || 'Unsplash');
          }
        }
        setActiveTab('story');
        return;
      }
      if (parts[0] === 'tv') {
        setActiveTab('tv');
        return;
      }
      if (parts[0] === 'studio') {
        if (!studioUser) {
          setActiveTab('loginStudio');
        } else {
          setActiveTab('cms');
        }
        return;
      }
      if (parts[0] === 'artikel' && parts[1]) {
        const found = articles.find((a) => a.id === parts[1]);
        if (found) {
          setDetailArticle(found);
          setActiveTab('detail');
          return;
        }
      }

      // Route: /#/:username/:slug/render
      if (parts.length >= 3 && (parts[2] === 'render' || parts[2] === 'story')) {
        const [username, articleSlug] = parts;
        const found = articles.find((a) => {
          const aUsername = getAuthorUsername(a);
          const aSlug = slugify(a.title);
          return (
            (aUsername === username || a.id === username) &&
            (aSlug === articleSlug || a.id === articleSlug || articleSlug.includes(a.id))
          ) || a.id === articleSlug || slugify(a.title) === articleSlug;
        });

        if (found) {
          setStoryArticle(found);
          setStoryCustomImage(found.image);
          setStoryMainText(found.title);
          let initialCredit = found.imageCredit || '';
          if (!initialCredit && found.caption && found.caption.toLowerCase().includes('unsplash')) {
            initialCredit = 'Unsplash';
          }
          setStoryCreditText(initialCredit || found.author || 'Unsplash');
          setActiveTab('story');
          return;
        }
      }

      if (parts.length === 1 && parts[0]) {
        // Link Penulis: /#/:username
        const username = parts[0].toLowerCase();
        const authorArticles = articles.filter(
          (a) =>
            (a.authorEmail && a.authorEmail.split('@')[0].toLowerCase() === username) ||
            slugify(a.author) === username ||
            a.author.toLowerCase().includes(username)
        );
        if (authorArticles.length > 0) {
          setSelectedAuthor({
            name: authorArticles[0].author,
            username,
            email: authorArticles[0].authorEmail || `${username}@gnn.id`,
          });
          setActiveTab('author');
        } else {
          setActiveTab('home');
        }
      } else if (parts.length >= 2) {
        // Link Artikel: /#/:username/:judul-artikel
        const [username, articleSlug] = parts;
        const found = articles.find((a) => {
          const aUsername = getAuthorUsername(a);
          const aSlug = slugify(a.title);
          return (
            (aUsername === username || a.id === username) &&
            (aSlug === articleSlug || a.id === articleSlug || articleSlug.includes(a.id))
          ) || a.id === articleSlug || slugify(a.title) === articleSlug;
        });

        if (found) {
          setDetailArticle(found);
          setActiveTab('detail');
        }
      }
    };

    handleHash();
    window.addEventListener('hashchange', handleHash);
    return () => window.removeEventListener('hashchange', handleHash);
  }, [articles, studioUser]);

  const selectArticleForStory = (art: Article) => {
    const aUsername = getAuthorUsername(art);
    const aSlug = slugify(art.title);

    setStoryArticle(art);
    setStoryCustomImage(art.image);
    setStoryHighlightText('');
    setStoryMainText(art.title || '');

    // Determine initial credit
    let initialCredit = art.imageCredit || '';
    if (!initialCredit && art.caption) {
      if (art.caption.toLowerCase().includes('unsplash')) {
        initialCredit = 'Unsplash';
      } else {
        const match = art.caption.match(/\((?:Dokumentasi|Foto|Sumber|Kredit|GNN)?\s*\/?\s*([^)]+)\)/i);
        if (match) initialCredit = match[1].trim();
      }
    }
    if (!initialCredit) {
      initialCredit = art.author || 'Unsplash';
    }
    setStoryCreditText(initialCredit);

    setActiveTab('story');
    window.location.hash = `#/${aUsername}/${aSlug}/render`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const fetchArticles = async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/news');
      const data = await res.json();
      if (data.success && Array.isArray(data.data) && data.data.length > 0) {
        // Filter published articles for public readers (case-insensitive)
        const publishedOnly = data.data.filter((a: any) => 
          !a.status || 
          ['publish', 'published', 'active'].includes(String(a.status).toLowerCase())
        );
        const finalArticles = publishedOnly.length > 0 ? publishedOnly : data.data;
        setArticles(finalArticles);
        setTrendingTopics(data.trendingTopics || []);
        setLiveSchedule(data.liveSchedule || []);
        if (finalArticles.length > 0 && !storyArticle) {
          setStoryArticle(finalArticles[0]);
          setStoryCreditText(finalArticles[0].imageCredit || 'Unsplash');
        }
      }
    } catch (e) {
      console.error('Gagal mengambil berita:', e);
    } finally {
      setLoading(false);
    }
  };

  // Like handler
  const handleLike = async (id: string, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    try {
      const res = await fetch(`/api/news/${id}/like`, { method: 'POST' });
      const data = await res.json();
      if (data.success) {
        setArticles(prev => prev.map(a => a.id === id ? { ...a, likes: data.likes } : a));
        if (detailArticle && detailArticle.id === id) {
          setDetailArticle(prev => prev ? { ...prev, likes: data.likes } : null);
        }
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Comment submission
  const handleCommentSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!detailArticle || !commentName.trim() || !commentText.trim()) return;

    setSubmittingComment(true);
    try {
      const res = await fetch(`/api/news/${detailArticle.id}/comment`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user: commentName, text: commentText }),
      });
      const data = await res.json();
      if (data.success) {
        const updated = [data.comment, ...(detailArticle.comments || [])];
        setDetailArticle({ ...detailArticle, comments: updated });
        setArticles(prev => prev.map(a => a.id === detailArticle.id ? { ...a, comments: updated } : a));
        setCommentText('');
      }
    } catch (err) {
      console.error(err);
    } finally {
      setSubmittingComment(false);
    }
  };

  // Bookmark toggle
  const toggleBookmark = (id: string, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    setBookmarkedIds(prev => prev.includes(id) ? prev.filter(item => item !== id) : [...prev, id]);
  };

  // Navigate to reader with requested /#/:username/:judul-artikel link format
  const openDetail = (art: Article) => {
    setDetailArticle(art);
    setActiveTab('detail');
    const username = getAuthorUsername(art);
    const slug = slugify(art.title);
    window.location.hash = `#/${username}/${slug}`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // Navigate to author profile with /#/:username format
  const openAuthorProfile = (artOrName: Article | string, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    let name = '';
    let username = '';
    if (typeof artOrName === 'string') {
      name = artOrName;
      username = slugify(artOrName);
    } else {
      name = artOrName.author;
      username = getAuthorUsername(artOrName);
    }
    setSelectedAuthor({ name, username, email: `${username}@gnn.id` });
    setActiveTab('author');
    window.location.hash = `#/${username}`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // Copy article link
  const copyShareLink = (art: Article) => {
    navigator.clipboard.writeText(window.location.href);
    setCopySuccess(true);
    setTimeout(() => setCopySuccess(false), 2500);
  };

  // Export Render Desain as PNG (native browser rendering via html-to-image for 100% pixel-exact output)
  const downloadStoryPNG = async () => {
    if (!storyPreviewRef.current) return;
    setIsExportingStory(true);

    const targetEl = storyPreviewRef.current;
    const editables = Array.from(targetEl.querySelectorAll('[contenteditable]'));

    try {
      // 1. Ensure custom web fonts (Roboto Serif) are ready
      if (document.fonts) {
        await document.fonts.ready;
      }

      // 2. Temporarily disable contenteditable during capture to prevent browser selection/cursor padding shifts
      editables.forEach((el) => {
        el.setAttribute('contenteditable', 'false');
      });

      await new Promise((res) => setTimeout(res, 150));

      // 3. Render using native browser SVG foreignObject renderer (html-to-image)
      const dataUrl = await toPng(targetEl, {
        pixelRatio: 3,
        cacheBust: true,
        backgroundColor: '#000000',
      });

      const a = document.createElement('a');
      a.href = dataUrl;
      a.download = `Render-Desain-${(storyHighlightText || 'Story').replace(/[^a-zA-Z0-9]/g, '')}-${Date.now()}.png`;
      a.click();
    } catch (err) {
      console.error('html-to-image export error, trying html2canvas fallback:', err);
      try {
        const canvas = await html2canvas(targetEl, {
          scale: 3,
          useCORS: true,
          backgroundColor: '#000000',
          logging: false,
        });
        const url = canvas.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = url;
        a.download = `Render-Desain-${(storyHighlightText || 'Story').replace(/[^a-zA-Z0-9]/g, '')}-${Date.now()}.png`;
        a.click();
      } catch (fallbackErr) {
        alert('Gagal mengekspor gambar render: ' + (fallbackErr instanceof Error ? fallbackErr.message : String(fallbackErr)));
      }
    } finally {
      // 4. Restore contenteditable attributes for live editing
      editables.forEach((el) => {
        el.setAttribute('contenteditable', 'true');
      });
      setIsExportingStory(false);
    }
  };

  // AI Generate article
  const handleAIGenerate = async () => {
    if (!aiTopic.trim()) return;
    setGeneratingAI(true);
    try {
      const res = await fetch('/api/news/generate-ai', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ topic: aiTopic, category: cmsCategory }),
      });
      const data = await res.json();
      if (data.success && data.data) {
        setArticles(prev => [data.data, ...prev]);
        setAiTopic('');
        openDetail(data.data);
      } else {
        alert(data.message || 'Gagal membuat berita AI.');
      }
    } catch (err) {
      console.error(err);
      alert('Terjadi kesalahan koneksi AI.');
    } finally {
      setGeneratingAI(false);
    }
  };

  // Manual CMS submit
  const handleManualSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!cmsTitle || !cmsContent) {
      alert('Judul dan isi berita wajib diisi.');
      return;
    }
    setSavingArticle(true);
    try {
      const filteredBullets = cmsBullets.filter(b => b.trim() !== '');
      const res = await fetch('/api/news', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: cmsTitle,
          content: cmsContent,
          category: cmsCategory,
          subCategory: cmsSubCategory,
          image: cmsImage,
          author: cmsAuthor,
          breaking: cmsBreaking,
          bullets: filteredBullets.length > 0 ? filteredBullets : [cmsTitle],
        }),
      });
      const data = await res.json();
      if (data.success && data.data) {
        setArticles(prev => [data.data, ...prev]);
        setCmsTitle('');
        setCmsContent('');
        setCmsBullets(['', '', '', '']);
        openDetail(data.data);
      }
    } catch (err) {
      console.error(err);
      alert('Gagal mempublikasikan artikel.');
    } finally {
      setSavingArticle(false);
    }
  };

  // Navigation categories with subcategories dropdown configuration
  const CATEGORY_NAV_ITEMS: { name: string; subcategories?: string[] }[] = [
    {
      name: 'Ekonomi',
      subcategories: ['Ekonomi', 'Bisnis', 'Investasi', 'Ketenagakerjaan'],
    },
    {
      name: 'Saintek',
      subcategories: ['Pendidikan', 'Teknologi'],
    },
    {
      name: 'Lingkungan',
    },
    {
      name: 'Politik',
      subcategories: ['Kebijakan', 'Nasional', 'Asean', 'Internasional'],
    },
    {
      name: 'Hukum',
      subcategories: ['Hukum', 'Kriminal', 'Investigasi'],
    },
    {
      name: 'Sosial',
      subcategories: ['Sosial', 'Budaya', 'Gastronomi', 'Seni & Sastra'],
    },
    {
      name: 'Lifestyle',
      subcategories: ['Religi', 'Olahraga', 'Hiburan', 'Kesehatan'],
    },
  ];

  const SUB_CATEGORIES = [
    'SEMUA',
    'Ekonomi',
    'Bisnis',
    'Investasi',
    'Ketenagakerjaan',
    'Pendidikan',
    'Teknologi',
    'Lingkungan',
    'Kebijakan',
    'Nasional',
    'Asean',
    'Internasional',
    'Hukum',
    'Kriminal',
    'Investigasi',
    'Sosial',
    'Budaya',
    'Gastronomi',
    'Seni & Sastra',
    'Religi',
    'Olahraga',
    'Hiburan',
    'Kesehatan',
  ];

  // Filtered articles (Comprehensive search)
  const filteredArticles = articles.filter(a => {
    const q = searchQuery.toLowerCase().trim();
    const matchQuery = !q || 
      a.title.toLowerCase().includes(q) || 
      a.content.toLowerCase().includes(q) ||
      (a.author && a.author.toLowerCase().includes(q)) ||
      (a.category && a.category.toLowerCase().includes(q)) ||
      (a.subCategory && a.subCategory.toLowerCase().includes(q)) ||
      (a.location && a.location.toLowerCase().includes(q)) ||
      (a.editor && a.editor.toLowerCase().includes(q)) ||
      (a.bullets && a.bullets.some(b => b.toLowerCase().includes(q)));

    if (selectedCategory === 'SEMUA') {
      return matchQuery;
    }

    const sel = selectedCategory.toLowerCase();
    const artCat = (a.category || '').toLowerCase();
    const artSub = (a.subCategory || '').toLowerCase();
    const artTitle = (a.title || '').toLowerCase();
    const artContent = (a.content || '').toLowerCase();

    // Map parent categories to their relevant keywords/subcategories
    const categoryMap: Record<string, string[]> = {
      'ekonomi': ['ekonomi', 'bisnis', 'investasi', 'ketenagakerjaan'],
      'saintek': ['saintek', 'pendidikan', 'teknologi', 'iptek', 'sains'],
      'lingkungan': ['lingkungan', 'alam', 'iklim', 'konservasi', 'hutan', 'cuaca'],
      'politik': ['politik', 'kebijakan', 'nasional', 'asean', 'internasional', 'pemerintah', 'dpr'],
      'hukum': ['hukum', 'kriminal', 'investigasi', 'sidang', 'kejaksaan', 'polisi'],
      'sosial': ['sosial', 'budaya', 'gastronomi', 'seni & sastra', 'seni', 'sastra', 'kuliner', 'tradisi', 'sejarah'],
      'lifestyle': ['lifestyle', 'gaya hidup', 'religi', 'olahraga', 'hiburan', 'kesehatan', 'musik', 'film', 'wisata']
    };

    const searchTerms = categoryMap[sel] || [sel];

    const matchCat = searchTerms.some(term => 
      artCat.includes(term) || 
      artSub.includes(term) ||
      artTitle.includes(term) ||
      artContent.includes(term)
    );

    return matchCat && matchQuery;
  });

  // Filtered videos (Comprehensive video search)
  const filteredVideos = videos.filter((v) => {
    const q = searchQuery.toLowerCase().trim();
    if (!q) return true;
    return (
      v.title.toLowerCase().includes(q) ||
      v.description.toLowerCase().includes(q) ||
      v.category.toLowerCase().includes(q) ||
      (v.subCategory && v.subCategory.toLowerCase().includes(q)) ||
      v.author.toLowerCase().includes(q)
    );
  });

  const featuredArticle = articles.find(a => a.featured) || articles[0];
  const sideArticles = articles.filter(a => a.id !== featuredArticle?.id).slice(0, 4);
  const popularArticles = [...articles].sort((a, b) => (b.views || 0) - (a.views || 0)).slice(0, 5);
  const breakingArticles = articles.filter(a => a.breaking);

  const CATEGORIES = [
    'SEMUA',
    'NASIONAL',
    'INTERNASIONAL',
    'EKONOMI',
    'OLAHRAGA',
    'TEKNOLOGI',
    'HIBURAN',
    'GAYA HIDUP',
  ];

  const [showLoginPassword, setShowLoginPassword] = useState(false);
  const [loginLoading, setLoginLoading] = useState(false);

  const handleStudioLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoginError('');
    if (!loginEmail || !loginPassword) {
      setLoginError('Email / Username dan Password wajib diisi.');
      return;
    }

    setLoginLoading(true);
    const idLower = loginEmail.toLowerCase().trim();

    // Direct client-side authentication for default accounts (Instant & 100% reliable)
    if ((idLower === 'admin' || idLower === 'admin@gnn.id') && loginPassword === 'admin') {
      const adminUser: User = {
        id: 'usr-1',
        username: 'admin',
        email: 'admin@gnn.id',
        name: 'Admin Utama GNN',
        role: 'admin',
        createdAt: '2026-01-10',
        status: 'active',
        avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
      };
      setStudioUser(adminUser);
      localStorage.setItem('studio_user', JSON.stringify(adminUser));
      setActiveTab('cms');
      setLoginPassword('');
      setLoginError('');
      setLoginLoading(false);
      return;
    } else if ((idLower === 'reviewer' || idLower === 'reviewer@gnn.id') && loginPassword === 'reviewer') {
      const reviewerUser: User = {
        id: 'usr-2',
        username: 'reviewer',
        email: 'reviewer@gnn.id',
        name: 'Redaktur Pelaksana (Reviewer)',
        role: 'reviewer',
        createdAt: '2026-02-15',
        status: 'active',
        avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
      };
      setStudioUser(reviewerUser);
      localStorage.setItem('studio_user', JSON.stringify(reviewerUser));
      setActiveTab('cms');
      setLoginPassword('');
      setLoginError('');
      setLoginLoading(false);
      return;
    } else if ((idLower === 'sahabat' || idLower === 'sahabat@gnn.id') && loginPassword === 'sahabat') {
      const sahabatUser: User = {
        id: 'usr-3',
        username: 'sahabat',
        email: 'sahabat@gnn.id',
        name: 'Sahabat Penulis GNN',
        role: 'sahabat',
        createdAt: '2026-03-01',
        status: 'active',
        avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=80',
      };
      setStudioUser(sahabatUser);
      localStorage.setItem('studio_user', JSON.stringify(sahabatUser));
      setActiveTab('cms');
      setLoginPassword('');
      setLoginError('');
      setLoginLoading(false);
      return;
    }

    try {
      const rawRes = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          identifier: loginEmail,
          password: loginPassword,
        }),
      });

      const text = await rawRes.text();
      let res: any = null;
      try {
        res = JSON.parse(text);
      } catch (e) {
        setLoginError('Login gagal. Periksa Email/Username dan Password.');
        return;
      }

      if (res && res.success && res.data) {
        setStudioUser(res.data);
        localStorage.setItem('studio_user', JSON.stringify(res.data));
        setActiveTab('cms');
        setLoginPassword('');
        setLoginError('');
      } else {
        setLoginError((res && res.message) || 'Login gagal. Periksa email/username dan password.');
      }
    } catch (err: any) {
      setLoginError('Gagal menghubungi server. Silakan coba lagi.');
    } finally {
      setLoginLoading(false);
    }
  };

  // Full-page view for Studio Redaksi (without public portal header & footer)
  if (activeTab === 'cms' || activeTab === 'loginStudio') {
    if (!studioUser) {
      return (
        <div className={`min-h-screen font-sans flex items-center justify-center p-4 transition-colors duration-200 ${isDarkMode ? 'dark bg-zinc-950 text-zinc-100' : 'bg-zinc-100 text-zinc-900'}`}>
          <div className="w-full max-w-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
            <div className="text-center space-y-2">
              <div className="flex justify-center mb-2">
                <GnfiLogo isDarkMode={isDarkMode} className="h-10 w-auto" />
              </div>
              <div className="inline-block px-3 py-1 bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 font-bold text-xs rounded-full border border-red-200/50">
                Studio Redaksi GNN
              </div>
              <h2 className="text-xl font-black text-zinc-900 dark:text-zinc-100">
                Masuk ke Studio
              </h2>
              <p className="text-xs text-zinc-500 dark:text-zinc-400">
                Masukkan Email atau Username dan Password untuk mengakses sistem redaksi.
              </p>
            </div>

            {loginError && (
              <div className="p-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-xs rounded-xl font-medium">
                {loginError}
              </div>
            )}

            <form onSubmit={handleStudioLogin} className="space-y-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                  Email atau Username
                </label>
                <div className="relative">
                  <AtSign className="w-4 h-4 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                  <input
                    type="text"
                    required
                    placeholder="admin@gnn.id atau username"
                    value={loginEmail}
                    onChange={(e) => setLoginEmail(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl pl-10 pr-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                  Password
                </label>
                <div className="relative">
                  <Lock className="w-4 h-4 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                  <input
                    type={showLoginPassword ? 'text' : 'password'}
                    required
                    placeholder="Masukkan kata sandi..."
                    value={loginPassword}
                    onChange={(e) => setLoginPassword(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl pl-10 pr-10 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  />
                  <button
                    type="button"
                    onClick={() => setShowLoginPassword(!showLoginPassword)}
                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition cursor-pointer"
                  >
                    {showLoginPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              </div>

              <button
                type="submit"
                disabled={loginLoading}
                className="w-full py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-md transition cursor-pointer disabled:opacity-50"
              >
                {loginLoading ? 'Memeriksa Kredensial...' : 'Masuk ke Studio'}
              </button>
            </form>

            <div className="relative flex py-1 items-center">
              <div className="flex-grow border-t border-zinc-200 dark:border-zinc-800"></div>
              <span className="flex-shrink mx-3 text-[10px] text-zinc-400 font-bold uppercase">atau masuk dengan</span>
              <div className="flex-grow border-t border-zinc-200 dark:border-zinc-800"></div>
            </div>

            <button
              type="button"
              onClick={() => {
                setLoginLoading(true);
                setLoginError('');

                const processRealGoogleProfile = async (email: string, name: string, picture?: string) => {
                  try {
                    const rawRes = await fetch('/api/auth/google', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({ email, name, picture }),
                    });

                    const contentType = rawRes.headers.get('content-type') || '';
                    if (!contentType.includes('application/json')) {
                      const fallbackUser: User = {
                        id: 'usr-google-' + Date.now(),
                        username: (name ? name.toLowerCase().replace(/[^a-z0-9]/g, '') : 'sahabat') + Math.floor(1000 + Math.random() * 9000),
                        email: email,
                        name: name || 'Sahabat Google GNN',
                        role: 'sahabat' as Role,
                        createdAt: new Date().toISOString().split('T')[0],
                        status: 'active',
                        avatar: picture || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=200&q=80',
                      };
                      setStudioUser(fallbackUser);
                      localStorage.setItem('studio_user', JSON.stringify(fallbackUser));
                      setActiveTab('cms');
                      return;
                    }

                    const res = await rawRes.json();
                    if (res && res.success && res.data) {
                      setStudioUser(res.data);
                      localStorage.setItem('studio_user', JSON.stringify(res.data));
                      setActiveTab('cms');
                    } else {
                      setLoginError((res && res.message) || 'Login Google gagal.');
                    }
                  } catch (e: any) {
                    const fallbackUser: User = {
                      id: 'usr-google-' + Date.now(),
                      username: (name ? name.toLowerCase().replace(/[^a-z0-9]/g, '') : 'sahabat') + Math.floor(1000 + Math.random() * 9000),
                      email: email,
                      name: name || 'Sahabat Google GNN',
                      role: 'sahabat' as Role,
                      createdAt: new Date().toISOString().split('T')[0],
                      status: 'active',
                      avatar: picture || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=200&q=80',
                    };
                    setStudioUser(fallbackUser);
                    localStorage.setItem('studio_user', JSON.stringify(fallbackUser));
                    setActiveTab('cms');
                  } finally {
                    setLoginLoading(false);
                  }
                };

                const googleObj = (window as any).google;
                if (googleObj && googleObj.accounts && googleObj.accounts.oauth2) {
                  try {
                    const client = googleObj.accounts.oauth2.initTokenClient({
                      client_id: '898618797755-50lm3nni6pst91vjhv9vrvv2olo602j1.apps.googleusercontent.com',
                      scope: 'email profile openid',
                      callback: async (tokenResponse: any) => {
                        if (tokenResponse.error) {
                          setLoginLoading(false);
                          setLoginError('Login Google dibatalkan.');
                          return;
                        }
                        try {
                          const userInfo = await fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
                            headers: { Authorization: `Bearer ${tokenResponse.access_token}` },
                          }).then((r) => r.json());

                          if (userInfo && userInfo.email) {
                            await processRealGoogleProfile(userInfo.email, userInfo.name || userInfo.email.split('@')[0], userInfo.picture);
                          } else {
                            throw new Error('Gagal mendapatkan profil dari Google');
                          }
                        } catch (err: any) {
                          setLoginLoading(false);
                          setLoginError('Gagal mengambil profil Google: ' + err.message);
                        }
                      },
                    });
                    client.requestAccessToken();
                  } catch (err) {
                    const realEmail = prompt('Masukkan Email Google Anda (contoh: nama.anda@gmail.com):');
                    if (realEmail && realEmail.trim()) {
                      const realName = prompt('Masukkan Nama Lengkap Anda:', realEmail.split('@')[0]) || realEmail.split('@')[0];
                      processRealGoogleProfile(realEmail.trim(), realName.trim());
                    } else {
                      setLoginLoading(false);
                    }
                  }
                } else {
                  const realEmail = prompt('Masukkan Email Google Anda (contoh: nama.anda@gmail.com):');
                  if (realEmail && realEmail.trim()) {
                    const realName = prompt('Masukkan Nama Lengkap Anda:', realEmail.split('@')[0]) || realEmail.split('@')[0];
                    processRealGoogleProfile(realEmail.trim(), realName.trim());
                  } else {
                    setLoginLoading(false);
                  }
                }
              }}
              className="w-full py-2.5 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-750 text-zinc-700 dark:text-zinc-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center justify-center gap-2 cursor-pointer"
            >
              <svg className="w-4 h-4" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
              </svg>
              <span>Lanjutkan dengan Akun Google</span>
            </button>


            <div className="pt-2 border-t border-zinc-100 dark:border-zinc-800 text-center space-y-2">
              <button
                type="button"
                onClick={() => setShowRegisterModal(true)}
                className="text-xs font-bold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 flex items-center justify-center gap-1 mx-auto transition cursor-pointer"
              >
                <span>Belum punya akun Sahabat?</span>
                <span className="underline">Daftar Sahabat GNN Sekarang</span>
              </button>
              <button
                type="button"
                onClick={() => {
                  setActiveTab('home');
                  window.location.hash = '#/';
                }}
                className="text-xs font-bold text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 flex items-center justify-center gap-1.5 mx-auto transition cursor-pointer"
              >
                <ArrowLeft className="w-3.5 h-3.5" /> Kembali ke Portal Publik
              </button>
            </div>
          </div>

          <SahabatRegisterModal
            isOpen={showRegisterModal}
            onClose={() => setShowRegisterModal(false)}
            onSuccessRegister={(newUser) => {
              setStudioUser(newUser);
              localStorage.setItem('studio_user', JSON.stringify(newUser));
              setActiveTab('cms');
            }}
          />
        </div>
      );
    }

    return (
      <div className={`min-h-screen font-sans transition-colors duration-200 ${isDarkMode ? 'dark bg-zinc-950 text-zinc-100' : 'bg-zinc-50 text-zinc-900'}`}>
        <StudioDashboard
          currentUser={{
            id: (studioUser as any)?.id || 'usr-current',
            username: (studioUser as any)?.username || 'admin',
            email: studioUser.email,
            name: studioUser.name,
            role: ((studioUser as any)?.role as Role) || 'admin',
            createdAt: (studioUser as any)?.createdAt || '2026-09-30',
            status: 'active',
          }}
          onSwitchUserRole={(newRole) => {
            const updated = {
              ...studioUser,
              role: newRole,
            };
            setStudioUser(updated);
            try {
              localStorage.setItem('studio_user', JSON.stringify(updated));
            } catch (e) {}
          }}
          onBackToPortal={() => {
            setActiveTab('home');
            window.location.hash = '#/';
            window.scrollTo({ top: 0, behavior: 'smooth' });
          }}
          onLogout={() => {
            try {
              localStorage.removeItem('studio_user');
            } catch (e) {}
            setStudioUser(null);
            setActiveTab('loginStudio');
            window.location.hash = '#/studio';
          }}
        />
      </div>
    );
  }

  return (
    <div className={`min-h-screen font-sans flex flex-col transition-colors duration-200 ${isDarkMode ? 'dark bg-zinc-950 text-zinc-100' : 'bg-white text-zinc-900'}`}>

      {/* ============================================================== */}
      {/* GNFI OFFICIAL 2-TIER HEADER (EXACTLY MATCHING USER SCREENSHOT) */}
      {/* ============================================================== */}
      <header className={`border-b sticky top-0 z-40 shadow-xs transition-colors duration-200 ${isDarkMode ? 'bg-zinc-900 border-zinc-800' : 'bg-white border-zinc-200'}`}>
        
        {/* Tier 1: Logo + Main Navigation Links + Search + Moon + Tulis Button */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between gap-4 md:gap-6 h-14 sm:h-16 md:h-20">
          
          {/* Left: GNFI Emblem Logo (Stretches to top edge & auto fits header height) */}
          <div
            onClick={() => {
              setActiveTab('home');
              setSelectedCategory('SEMUA');
              setSearchQuery('');
            }}
            className="flex items-center cursor-pointer select-none shrink-0 group transition-transform hover:scale-102 h-full py-1 self-stretch"
            title="Good News Nusantara"
          >
            <GnfiLogo isDarkMode={isDarkMode} className="h-full w-auto max-h-full py-0.5" />
          </div>

          {/* Center: Top Navigation Links with Subcategory Dropdowns (Desktop) */}
          <nav className="hidden lg:flex items-center gap-5 xl:gap-7 flex-1 pl-2 xl:pl-4">
            {CATEGORY_NAV_ITEMS.map((item) => {
              const isActive = selectedCategory === item.name || (item.subcategories && item.subcategories.includes(selectedCategory));
              return (
                <div key={item.name} className="relative group py-2">
                  <button
                    onClick={() => {
                      setSelectedCategory(item.name);
                      setActiveTab('home');
                      setSearchQuery('');
                    }}
                    className={`text-[15px] font-bold transition-all flex items-center gap-1 cursor-pointer whitespace-nowrap ${
                      isActive
                        ? 'text-[#E64A19]'
                        : isDarkMode
                        ? 'text-zinc-200 hover:text-[#E64A19]'
                        : 'text-zinc-900 hover:text-[#E64A19]'
                    }`}
                  >
                    <span>{item.name}</span>
                    {item.subcategories && item.subcategories.length > 0 && (
                      <ChevronDown className="w-3.5 h-3.5 stroke-[2.5] transition-transform duration-200 group-hover:rotate-180 opacity-75" />
                    )}
                    {isActive && (
                      <span className="absolute -bottom-0.5 left-0 right-0 h-[3px] bg-[#E64A19] rounded-full" />
                    )}
                  </button>

                  {/* Dropdown Menu for Sub-Categories */}
                  {item.subcategories && item.subcategories.length > 0 && (
                    <div className="absolute top-full left-0 mt-0 w-48 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xl py-2 z-50 hidden group-hover:block transition-all duration-150 animate-in fade-in">
                      {item.subcategories.map((sub) => {
                        const isSubActive = selectedCategory === sub;
                        return (
                          <button
                            key={sub}
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedCategory(sub);
                              setActiveTab('home');
                              setSearchQuery('');
                            }}
                            className={`w-full text-left px-4 py-2 text-xs sm:text-sm font-semibold transition-colors flex items-center justify-between ${
                              isSubActive
                                ? 'text-[#E64A19] bg-orange-50/70 dark:bg-orange-950/30'
                                : isDarkMode
                                ? 'text-zinc-300 hover:text-white hover:bg-zinc-800/80'
                                : 'text-zinc-700 hover:text-zinc-950 hover:bg-zinc-100'
                            }`}
                          >
                            <span>{sub}</span>
                            {isSubActive && <span className="w-1.5 h-1.5 rounded-full bg-[#E64A19]" />}
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </nav>

          {/* Right: Inline Search Input, Dark Mode Toggle, Tulis Button & Mobile Toggle */}
          <div className="flex items-center gap-2 sm:gap-3 shrink-0">
            {/* Inline Header Search Box (Desktop & Tablet) */}
            <div className="relative hidden sm:flex items-center min-w-[170px] max-w-[220px] lg:max-w-[250px] w-full">
              <input
                type="text"
                placeholder="Cari berita..."
                value={searchQuery}
                onChange={(e) => {
                  setSearchQuery(e.target.value);
                  if (activeTab === 'detail') setActiveTab('home');
                }}
                className={`w-full border rounded-full py-1.5 pl-3.5 pr-8 text-xs font-medium focus:outline-none focus:border-[#CC0000] transition-all ${
                  isDarkMode
                    ? 'bg-zinc-800/90 border-zinc-700 text-white placeholder-zinc-400 focus:bg-zinc-900'
                    : 'bg-zinc-100 border-zinc-200 text-zinc-900 placeholder-zinc-500 focus:bg-white'
                }`}
              />
              <Search className="w-3.5 h-3.5 text-zinc-400 absolute right-3 pointer-events-none" />
              {searchQuery && (
                <button
                  onClick={() => setSearchQuery('')}
                  className="absolute right-7 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 cursor-pointer"
                  title="Hapus Pencarian"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              )}
            </div>

            {/* Dark Mode (Moon/Sun) Toggle Button */}
            <button
              onClick={() => setIsDarkMode(!isDarkMode)}
              className={`p-1.5 transition-colors cursor-pointer rounded-full ${isDarkMode ? 'text-amber-400 hover:text-amber-300 hover:bg-zinc-800' : 'text-zinc-700 hover:text-zinc-950 hover:bg-zinc-100'}`}
              title={isDarkMode ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'}
            >
              {isDarkMode ? (
                <Sun className="w-5 h-5 stroke-[2.2]" />
              ) : (
                <Moon className="w-5 h-5 stroke-[2.2]" />
              )}
            </button>

            {/* Tulis Button (Single Action Button) */}
            <button
              onClick={() => {
                if (studioUser) {
                  setActiveTab('cms');
                } else {
                  setActiveTab('loginStudio');
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
              }}
              className="bg-[#CC0000] hover:bg-red-700 text-white rounded-lg px-3.5 sm:px-4 py-1.5 text-xs sm:text-sm font-bold transition-colors cursor-pointer shadow-xs flex items-center gap-1.5"
              title="Tulis Berita Baru / Masuk Studio"
            >
              <span>Tulis</span>
            </button>

            {/* Mobile Hamburger Toggle */}
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className={`p-1.5 lg:hidden cursor-pointer ${isDarkMode ? 'text-zinc-200 hover:text-white' : 'text-zinc-700 hover:text-zinc-950'}`}
              aria-label="Menu navigasi"
            >
              {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </div>

        {/* Mobile Navigation Drawer */}
        {mobileMenuOpen && (
          <div className={`lg:hidden border-t p-4 space-y-4 shadow-lg ${isDarkMode ? 'bg-zinc-900 border-zinc-800' : 'bg-white border-zinc-200'}`}>
            {/* Mobile Search Box */}
            <div className="relative w-full">
              <input
                type="text"
                placeholder="Cari topik / kata kunci berita..."
                value={searchQuery}
                onChange={(e) => {
                  setSearchQuery(e.target.value);
                  if (activeTab === 'detail') setActiveTab('home');
                }}
                className={`w-full border rounded-full py-2 pl-3.5 pr-9 text-xs font-medium focus:outline-none focus:border-[#CC0000] ${
                  isDarkMode
                    ? 'bg-zinc-800 border-zinc-700 text-white placeholder-zinc-400'
                    : 'bg-zinc-100 border-zinc-200 text-zinc-900 placeholder-zinc-500'
                }`}
              />
              <Search className="w-4 h-4 text-zinc-400 absolute right-3 top-2.5 pointer-events-none" />
              {searchQuery && (
                <button
                  onClick={() => setSearchQuery('')}
                  className="absolute right-8 top-2.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 cursor-pointer"
                >
                  <X className="w-4 h-4" />
                </button>
              )}
            </div>

            {/* Top Nav Sections on Mobile */}
            <div>
              <div className="font-bold text-xs text-zinc-400 uppercase tracking-wider mb-2">Kategori Utama</div>
              <div className="space-y-2">
                {CATEGORY_NAV_ITEMS.map((item) => {
                  const isActive = selectedCategory === item.name || (item.subcategories && item.subcategories.includes(selectedCategory));
                  return (
                    <div key={item.name} className="border rounded-lg p-2.5 bg-zinc-50/50 dark:bg-zinc-800/40 dark:border-zinc-800">
                      <button
                        onClick={() => {
                          setSelectedCategory(item.name);
                          setActiveTab('home');
                          setSearchQuery('');
                          setMobileMenuOpen(false);
                        }}
                        className={`w-full text-left text-sm font-bold flex items-center justify-between ${
                          isActive ? 'text-[#E64A19]' : isDarkMode ? 'text-zinc-200' : 'text-zinc-900'
                        }`}
                      >
                        <span>{item.name}</span>
                        {item.subcategories && <ChevronRight className="w-4 h-4 text-zinc-400" />}
                      </button>

                      {item.subcategories && (
                        <div className="mt-2 pl-2 border-l-2 border-orange-200 dark:border-zinc-700 flex flex-wrap gap-1.5 pt-1">
                          {item.subcategories.map(sub => (
                            <button
                              key={sub}
                              onClick={() => {
                                setSelectedCategory(sub);
                                setActiveTab('home');
                                setSearchQuery('');
                                setMobileMenuOpen(false);
                              }}
                              className={`text-xs px-2.5 py-1 rounded-md transition-colors ${
                                selectedCategory === sub
                                  ? 'bg-[#E64A19] text-white font-semibold'
                                  : isDarkMode
                                  ? 'bg-zinc-800 text-zinc-300 hover:text-white'
                                  : 'bg-white border text-zinc-700 hover:bg-zinc-100'
                              }`}
                            >
                              {sub}
                            </button>
                          ))}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Sub-categories on Mobile */}
            <div>
              <div className="font-bold text-xs text-zinc-400 uppercase tracking-wider mb-2">Kanal Rubrik</div>
              <div className="grid grid-cols-3 gap-1.5">
                {SUB_CATEGORIES.map((cat) => (
                  <button
                    key={cat}
                    onClick={() => {
                      setSelectedCategory(cat);
                      setActiveTab('home');
                      setMobileMenuOpen(false);
                    }}
                    className={`py-1.5 px-2 text-left text-[11px] font-medium rounded truncate ${
                      selectedCategory === cat
                        ? 'bg-orange-100 text-[#E64A19] font-bold'
                        : isDarkMode
                        ? 'text-zinc-300 hover:bg-zinc-800'
                        : 'text-zinc-700 hover:bg-zinc-100'
                    }`}
                  >
                    {cat}
                  </button>
                ))}
              </div>
            </div>

            {/* Quick Action buttons */}
            <div className="pt-2 border-t border-zinc-200 flex flex-col gap-2">
              <button
                onClick={() => {
                  setActiveTab('story');
                  setMobileMenuOpen(false);
                }}
                className="w-full text-left py-2 px-3 text-xs font-bold bg-[#E64A19] text-white rounded flex items-center gap-2"
              >
                <ImageIcon className="w-4 h-4" />
                <span>RENDER DESAIN INFOGRAFIK</span>
              </button>
              <button
                onClick={() => {
                  if (studioUser) {
                    setActiveTab('cms');
                  } else {
                    setActiveTab('loginStudio');
                  }
                  setMobileMenuOpen(false);
                }}
                className="w-full text-left py-2 px-3 text-xs font-bold border border-zinc-300 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 rounded flex items-center gap-2 cursor-pointer"
              >
                <Plus className="w-4 h-4" />
                <span>TULIS ARTIKEL BARU</span>
              </button>
            </div>
          </div>
        )}
      </header>

      {/* ============================================================== */}
      {/* 5. BREAKING NEWS MARQUEE TICKER (CNN SIGNATURE RED BAR) */}
      {/* ============================================================== */}
      <div className="bg-[#CC0000] text-white py-1.5 px-4 text-xs font-semibold flex items-center gap-3 overflow-hidden shadow-xs">
        <div className="bg-white text-[#CC0000] px-2.5 py-0.5 font-black uppercase text-[10px] tracking-wider shrink-0 rounded-xs flex items-center gap-1">
          <Flame className="w-3.5 h-3.5 fill-current" />
          <span>BREAKING NEWS</span>
        </div>
        
        <div className="overflow-hidden whitespace-nowrap flex-grow relative">
          <div className="animate-marquee-infinite flex gap-12 text-[12px] font-medium">
            {breakingArticles.concat(articles.slice(0, 4)).map((item, idx) => (
              <span
                key={idx}
                onClick={() => openDetail(item)}
                className="cursor-pointer hover:underline flex items-center gap-2 shrink-0"
              >
                <span className="font-bold text-yellow-300">[{item.category}]</span> {item.title}
                <span className="text-white/60">•</span>
              </span>
            ))}
          </div>
        </div>
      </div>

      {/* ============================================================== */}
      {/* 6. MAIN CONTENT AREA */}
      {/* ============================================================== */}
      <main className="flex-grow max-w-7xl mx-auto w-full px-4 py-6">

        {/* LOADING INDICATOR */}
        {loading && (
          <div className="flex flex-col items-center justify-center py-24 gap-3">
            <RefreshCw className="w-8 h-8 text-[#CC0000] animate-spin" />
            <p className="text-xs font-black uppercase tracking-widest text-zinc-500">Memuat Good News Nusantara...</p>
          </div>
        )}

        {/* ==================== VIEW 1: HOME PORTAL ==================== */}
        {!loading && activeTab === 'home' && (
          <div className="space-y-10">

            {/* A. SIGNATURE HERO GRID (CNN INDONESIA HEADLINE UTAMA) */}
            {selectedCategory === 'SEMUA' && !searchQuery && featuredArticle && (
              <section className="border-b border-zinc-200 dark:border-zinc-800 pb-8">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                  
                  {/* Lead Headline Story (8 columns) - Overlay Title on Cover with Gradient */}
                  <div
                    onClick={() => openDetail(featuredArticle)}
                    className="lg:col-span-8 cursor-pointer group relative aspect-video sm:aspect-16/9 w-full overflow-hidden rounded-xl bg-zinc-900 shadow-md border border-zinc-200 dark:border-zinc-800"
                  >
                    {/* Cover Image */}
                    <img
                      src={featuredArticle.image}
                      alt={featuredArticle.title}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                    />

                    {/* Black Gradient Overlay (from bottom to top) */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent flex flex-col justify-between p-4 sm:p-6 md:p-8">
                      {/* Top Bar: Headline Badge & Bookmark Button */}
                      <div className="flex items-center justify-between">
                        <span className="bg-[#E64A19] text-white text-[10px] sm:text-xs font-black uppercase px-3 py-1 rounded-md tracking-wider shadow-md">
                          HEADLINE UTAMA
                        </span>
                        <button
                          onClick={(e) => toggleBookmark(featuredArticle.id, e)}
                          className={`p-2 rounded-full shadow-md backdrop-blur-md transition-colors ${
                            bookmarkedIds.includes(featuredArticle.id)
                              ? 'bg-[#E64A19] text-white'
                              : 'bg-black/50 text-white hover:bg-black/80'
                          }`}
                        >
                          <Bookmark className="w-4 h-4 sm:w-5 sm:h-5" />
                        </button>
                      </div>

                      {/* Bottom Content Overlaid on Cover Image */}
                      <div className="flex flex-col gap-2 pt-12">
                        <span className="text-xs font-bold text-orange-400 uppercase tracking-widest">
                          {featuredArticle.category} {featuredArticle.subCategory ? `• ${featuredArticle.subCategory}` : ''}
                        </span>

                        <h1 className="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold text-white group-hover:text-orange-300 transition-colors tracking-tight leading-snug drop-shadow-md">
                          {featuredArticle.title}
                        </h1>

                        <div className="flex items-center gap-2 text-xs text-zinc-300 font-medium pt-1">
                          <span className="font-semibold text-white">{featuredArticle.author}</span>
                          <span>•</span>
                          <span>{featuredArticle.date}</span>
                          {featuredArticle.timeAgo && (
                            <>
                              <span>•</span>
                              <span>{featuredArticle.timeAgo}</span>
                            </>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Side Top Stories (4 columns - CNN Indonesia stacked layout) */}
                  <div className="lg:col-span-4 flex flex-col divide-y divide-zinc-200 dark:divide-zinc-800">
                    <div className="pb-2 mb-2 border-b-2 border-[#CC0000] flex items-center justify-between">
                      <h3 className="font-black text-xs uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                        BERITA UTAMA LAINNYA
                      </h3>
                      <span className="text-[10px] text-[#CC0000] font-bold">TERKINI</span>
                    </div>

                    {sideArticles.map((art) => (
                      <div
                        key={art.id}
                        onClick={() => openDetail(art)}
                        className="py-3.5 first:pt-0 cursor-pointer group flex gap-3 justify-between items-start"
                      >
                        <div className="flex-grow flex flex-col gap-1">
                          <span className="text-[10px] font-bold text-[#CC0000] uppercase tracking-wide">
                            {art.category}
                          </span>
                          <h4 className="text-sm font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] dark:group-hover:text-red-400 transition-colors leading-snug line-clamp-2">
                            {art.title}
                          </h4>
                          <div className="flex items-center gap-1.5 text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                            <span className="font-semibold text-zinc-700 dark:text-zinc-300">{art.author}</span>
                            <span>•</span>
                            <span>{art.date}</span>
                          </div>
                        </div>
                        <div className="w-24 h-16 rounded-xs overflow-hidden shrink-0 bg-zinc-100 dark:bg-zinc-800 shadow-2xs">
                          <img
                            src={art.image}
                            alt=""
                            className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                          />
                        </div>
                      </div>
                    ))}
                  </div>

                </div>
              </section>
            )}

            {/* ============================================================== */}
            {/* SPACE KHUSUS: GNN TV (NORMAL & SEARCH VIDEO RESULTS) */}
            {/* ============================================================== */}
            {((selectedCategory === 'SEMUA' && !searchQuery && videos.length > 0) || (searchQuery.trim() !== '' && filteredVideos.length > 0)) && (
              <section className="border-b border-zinc-200 dark:border-zinc-800 pb-10">
                {/* Header Section */}
                <div className="flex items-center justify-between pb-2 mb-6 border-b border-zinc-100 dark:border-zinc-800/60">
                  <div>
                    <h2 className="text-lg sm:text-xl font-black uppercase tracking-tight text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                      <Tv className="w-5 h-5 text-[#CC0000]" />
                      <span>{searchQuery ? `GNN TV - HASIL PENCARIAN VIDEO (${filteredVideos.length})` : 'GNN TV'}</span>
                    </h2>
                    <div className="w-12 h-1 bg-[#CC0000] mt-1.5 rounded-full" />
                  </div>

                  <button
                    onClick={() => {
                      setActiveTab('tv');
                      window.location.hash = '#tv';
                    }}
                    className="text-xs font-extrabold text-zinc-800 dark:text-zinc-200 hover:text-[#CC0000] dark:hover:text-red-400 uppercase tracking-wider flex items-center gap-1 transition-colors cursor-pointer"
                  >
                    <span>LIHAT SEMUA VIDEO</span>
                    <ChevronRight className="w-3.5 h-3.5 text-[#CC0000]" />
                  </button>
                </div>

                {/* Video Cards Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 sm:gap-8">
                  {(searchQuery ? filteredVideos : videos.slice(0, 4)).map((vid) => (
                    <article
                      key={vid.id}
                      onClick={() => setSelectedVideoModal(vid)}
                      className="group cursor-pointer flex flex-col items-start"
                    >
                      {/* Video Thumbnail with Play Button Overlay */}
                      <div className="aspect-video w-full rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 relative shadow-xs">
                        <img
                          src={`https://img.youtube.com/vi/${vid.youtubeId}/hqdefault.jpg`}
                          alt={vid.title}
                          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                          onError={(e) => {
                            (e.target as HTMLImageElement).src =
                              `https://img.youtube.com/vi/${vid.youtubeId}/mqdefault.jpg`;
                          }}
                        />
                        <div className="absolute inset-0 bg-black/20 group-hover:bg-black/10 flex items-center justify-center transition-colors">
                          <div className="w-11 h-11 rounded-full bg-[#CC0000] text-white flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                            <Play className="w-4 h-4 fill-current ml-0.5" />
                          </div>
                        </div>
                        {vid.duration && (
                          <span className="absolute bottom-2 right-2 bg-black/80 text-white text-[10px] font-mono px-1.5 py-0.5 rounded font-bold">
                            {vid.duration}
                          </span>
                        )}
                      </div>

                      {/* Video Title */}
                      <h3 className="font-bold text-base sm:text-[17px] text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] dark:group-hover:text-red-400 leading-snug line-clamp-2 mt-3.5 mb-1.5 transition-colors">
                        {vid.title}
                      </h3>

                      {/* Video Category in Red */}
                      <span className="text-xs sm:text-sm font-bold text-[#CC0000] dark:text-red-400 capitalize">
                        {vid.category}
                      </span>
                    </article>
                  ))}
                </div>
              </section>
            )}

            {/* B. SECTION 2: CNN TERKINI & TERPOPULER (MAIN FEED + SIDEBAR) */}
            <section className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
              
              {/* Left Column: Feed Berita Terkini (8 columns) */}
              <div className="lg:col-span-8 space-y-6">
                
                <div className="flex items-center justify-between border-b-2 border-zinc-900 dark:border-zinc-700 pb-2">
                  <div className="flex items-center gap-2">
                    <span className="w-2.5 h-6 bg-[#CC0000] inline-block"></span>
                    <h2 className="text-lg font-black uppercase tracking-tight text-zinc-950 dark:text-zinc-100">
                      {searchQuery
                        ? `HASIL PENCARIAN ARTIKEL (${filteredArticles.length})`
                        : selectedCategory === 'SEMUA'
                        ? 'BERITA TERKINI'
                        : `KABAR ${selectedCategory}`}
                    </h2>
                  </div>
                  <span className="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                    Menampilkan {filteredArticles.length} artikel
                  </span>
                </div>

                {filteredArticles.length === 0 && filteredVideos.length === 0 ? (
                  <div className="text-center py-16 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded">
                    <p className="text-sm font-bold text-zinc-600 dark:text-zinc-300">
                      Tidak ada berita atau video ditemukan untuk kata kunci "{searchQuery}".
                    </p>
                    <button
                      onClick={() => {
                        setSelectedCategory('SEMUA');
                        setSearchQuery('');
                      }}
                      className="mt-3 text-xs text-[#CC0000] font-bold hover:underline"
                    >
                      Kembali ke Semua Berita & Video
                    </button>
                  </div>
                ) : (
                  <div className="space-y-4">
                    <div className="divide-y divide-zinc-200 dark:divide-zinc-800">
                      {(selectedCategory === 'SEMUA' && !searchQuery ? filteredArticles.slice(0, visibleCount) : filteredArticles).map((art) => (
                        <article
                          key={art.id}
                          onClick={() => openDetail(art)}
                          className="py-4 first:pt-0 cursor-pointer group grid grid-cols-12 gap-4 items-center"
                        >
                          {/* Thumbnail image / cover (4 cols) */}
                          <div className="col-span-4 sm:col-span-3 aspect-4/3 rounded-xs overflow-hidden bg-zinc-100 dark:bg-zinc-800 relative">
                            <img
                              src={art.image}
                              alt={art.title}
                              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                              loading="lazy"
                            />
                            {art.breaking && (
                              <span className="absolute top-1 left-1 bg-[#CC0000] text-white text-[8px] font-black uppercase px-1.5 py-0.5">
                                BREAKING
                              </span>
                            )}
                          </div>

                          {/* Title & metadata (8 cols) - Only Category, Title, Author & Date */}
                          <div className="col-span-8 sm:col-span-9 flex flex-col justify-center gap-1">
                            <span className="text-[10px] font-bold tracking-wider uppercase text-[#CC0000]">
                              {art.category}
                            </span>

                            <h3 className="text-sm sm:text-base font-bold text-zinc-950 dark:text-zinc-100 group-hover:text-[#CC0000] dark:group-hover:text-red-400 transition-colors leading-snug line-clamp-2">
                              {art.title}
                            </h3>

                            <div className="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                              <span className="font-semibold text-zinc-700 dark:text-zinc-300">{art.author}</span>
                              <span>•</span>
                              <span>{art.date}</span>
                            </div>
                          </div>
                        </article>
                      ))}
                    </div>

                    {selectedCategory === 'SEMUA' && !searchQuery && visibleCount < filteredArticles.length && (
                      <div className="pt-6 text-center border-t border-zinc-200 dark:border-zinc-800">
                        <button
                          onClick={() => setVisibleCount((prev) => prev + 6)}
                          className="px-6 py-2.5 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-900 dark:text-zinc-100 font-extrabold text-xs uppercase tracking-wider rounded-xl transition-all shadow-2xs inline-flex items-center justify-center gap-2 cursor-pointer"
                        >
                          <span>Muat Berita Lainnya</span>
                          <ChevronDown className="w-4 h-4 text-[#CC0000]" />
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>

              {/* Right Column: TERPOPULER (1 to 5) & CATEGORY WIDGETS (4 columns) */}
              <div className="lg:col-span-4 space-y-8 lg:sticky lg:top-24 lg:self-start">
                
                {/* 1. TERPOPULER WIDGET (CNN Signature Bold Red Numbers) */}
                <div className="bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 rounded-xs">
                  <div className="border-b-2 border-[#CC0000] pb-2 mb-4 flex items-center justify-between">
                    <h3 className="font-black text-sm uppercase tracking-wider text-zinc-950 dark:text-zinc-100 flex items-center gap-2">
                      <Flame className="w-4 h-4 text-[#CC0000] fill-current" />
                      TERPOPULER
                    </h3>
                    <span className="text-[10px] text-zinc-500 dark:text-zinc-400 font-bold">24 JAM TERAKHIR</span>
                  </div>

                  <div className="space-y-4">
                    {popularArticles.map((art, idx) => (
                      <div
                        key={art.id}
                        onClick={() => openDetail(art)}
                        className="flex items-start gap-3 cursor-pointer group pb-3 border-b border-zinc-200 dark:border-zinc-800 last:border-b-0 last:pb-0"
                      >
                        {/* Red numeral badge (1, 2, 3...) */}
                        <span className="font-display font-black text-2xl text-[#CC0000] leading-none shrink-0 w-6 text-center">
                          {idx + 1}
                        </span>
                        <div className="flex flex-col gap-1">
                          <span className="text-[10px] font-bold text-[#CC0000] uppercase tracking-wide">
                            {art.category}
                          </span>
                          <h4 className="text-xs font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] dark:group-hover:text-red-400 transition-colors leading-snug line-clamp-2">
                            {art.title}
                          </h4>
                          <div className="flex items-center gap-1.5 text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                            <span className="font-semibold text-zinc-700 dark:text-zinc-300">{art.author}</span>
                            <span>•</span>
                            <span>{art.date}</span>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* 2. CATEGORY NEWS BLOCKS (Ekonomi, Pendidikan, Budaya & Pariwisata) */}
                {[
                  { key: 'EKONOMI', label: 'EKONOMI & BISNIS', subFilter: ['Ekonomi', 'Bisnis', 'Investasi', 'Kemandirian Pangan'] },
                  { key: 'SAINTEK', label: 'PENDIDIKAN & SAINTEK', subFilter: ['Pendidikan', 'Teknologi', 'Saintek', 'Inovasi Pertanian'] },
                  { key: 'BUDAYA', label: 'BUDAYA & PARIWISATA', subFilter: ['Budaya', 'Pariwisata', 'Warisan Nusantara', 'Desa Wisata', 'Konservasi Fauna'] },
                ].map((catGroup) => {
                  const matched = articles.filter(
                    (a) =>
                      a.category === catGroup.key ||
                      a.category === catGroup.label ||
                      (a.subCategory && catGroup.subFilter.some((sf) => a.subCategory?.toLowerCase().includes(sf.toLowerCase()))) ||
                      catGroup.subFilter.some((sf) => a.category.toLowerCase().includes(sf.toLowerCase()))
                  );
                  const displayList = matched.slice(0, 3);
                  if (displayList.length === 0) return null;

                  const firstArt = displayList[0];
                  const otherArts = displayList.slice(1, 3);

                  return (
                    <div
                      key={catGroup.key}
                      className="bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 rounded-xs space-y-3"
                    >
                      {/* Section Header */}
                      <div className="border-b-2 border-[#CC0000] pb-2 flex items-center justify-between">
                        <h3 className="font-black text-xs sm:text-sm uppercase tracking-wider text-zinc-950 dark:text-zinc-100 flex items-center gap-1.5">
                          <span className="w-2 h-2 bg-[#CC0000] rounded-full"></span>
                          <span>{catGroup.label}</span>
                        </h3>
                        <button
                          onClick={() => {
                            setSelectedCategory(catGroup.key);
                            setActiveTab('home');
                          }}
                          className="text-[10px] font-bold text-[#CC0000] dark:text-red-400 hover:underline uppercase cursor-pointer"
                        >
                          Lihat Semua
                        </button>
                      </div>

                      {/* Article #1: Displays photo thumbnail */}
                      {firstArt && (
                        <div
                          onClick={() => openDetail(firstArt)}
                          className="group cursor-pointer space-y-2 pb-3 border-b border-zinc-200 dark:border-zinc-800"
                        >
                          <div className="aspect-video w-full rounded-lg overflow-hidden bg-zinc-200 dark:bg-zinc-800 relative">
                            <img
                              src={firstArt.image}
                              alt={firstArt.title}
                              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            />
                            <span className="absolute bottom-2 left-2 bg-[#CC0000] text-white text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded">
                              {firstArt.subCategory || firstArt.category}
                            </span>
                          </div>
                          <h4 className="font-bold text-sm text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] dark:group-hover:text-red-400 leading-snug line-clamp-2 transition-colors">
                            {firstArt.title}
                          </h4>
                          <div className="flex items-center gap-1.5 text-[10px] text-zinc-500 dark:text-zinc-400">
                            <span>{firstArt.author}</span>
                            <span>•</span>
                            <span>{firstArt.date}</span>
                          </div>
                        </div>
                      )}

                      {/* Articles #2 & #3: Text-only list */}
                      <div className="space-y-2.5 pt-1">
                        {otherArts.map((art) => (
                          <div
                            key={art.id}
                            onClick={() => openDetail(art)}
                            className="group cursor-pointer flex flex-col gap-0.5 pb-2.5 border-b border-zinc-200/70 dark:border-zinc-800/70 last:border-b-0 last:pb-0"
                          >
                            <h5 className="text-xs font-bold text-zinc-800 dark:text-zinc-200 group-hover:text-[#CC0000] dark:group-hover:text-red-400 leading-snug line-clamp-2 transition-colors">
                              {art.title}
                            </h5>
                            <div className="text-[10px] text-zinc-400 flex items-center gap-1">
                              <span className="truncate">{art.author}</span>
                              <span>•</span>
                              <span className="shrink-0">{art.date}</span>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  );
                })}

              </div>

            </section>

          </div>
        )}

        {/* ==================== VIEW 2: ARTICLE DETAIL / READING PAGE ==================== */}
        {!loading && activeTab === 'detail' && detailArticle && (
          <div className="max-w-4xl mx-auto space-y-6">
            
            {/* Breadcrumb Navigation */}
            <div className="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 font-medium border-b border-zinc-200 dark:border-zinc-800 pb-3">
              <button onClick={() => setActiveTab('home')} className="hover:text-[#CC0000] dark:hover:text-red-400">HOME</button>
              <ChevronRight className="w-3.5 h-3.5" />
              <button onClick={() => { setSelectedCategory(detailArticle.category); setActiveTab('home'); }} className="hover:text-[#CC0000] dark:hover:text-red-400 font-bold text-[#CC0000]">
                {detailArticle.category}
              </button>
              <ChevronRight className="w-3.5 h-3.5" />
              <span className="text-zinc-400 dark:text-zinc-500 truncate max-w-xs">{detailArticle.subCategory || 'TERKINI'}</span>
            </div>

            {/* ==================== 1. JUDUL ARTIKEL ==================== */}
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="bg-[#CC0000] text-white text-[10px] font-black uppercase px-2.5 py-0.5 tracking-wider rounded-xs">
                  {detailArticle.category}
                </span>
                {detailArticle.breaking && (
                  <span className="bg-yellow-400 text-zinc-950 text-[10px] font-black uppercase px-2.5 py-0.5 tracking-wider rounded-xs">
                    BREAKING
                  </span>
                )}
                <span className="text-xs text-zinc-500 dark:text-zinc-400 ml-auto font-mono">{detailArticle.timeAgo}</span>
              </div>

              <h1 className="text-2xl sm:text-3xl md:text-4xl font-extrabold text-zinc-950 dark:text-zinc-100 leading-tight tracking-tight">
                {detailArticle.title}
              </h1>

              {/* ==================== 2. NAMA PENULIS (CLICKABLE) & TANGGAL PUBLIKASI (TANPA KATA 'Penulis', BORDERLESS & TIPIS) ==================== */}
              <div className="flex flex-wrap items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400 pt-0.5 pb-1">
                <button
                  onClick={(e) => openAuthorProfile(detailArticle, e)}
                  className="font-bold text-[#CC0000] dark:text-red-400 hover:underline flex items-center gap-1 text-sm cursor-pointer"
                  title={`Lihat semua artikel karya ${detailArticle.author}`}
                >
                  {detailArticle.author}
                </button>
                <span className="text-zinc-400 font-mono">•</span>
                <span className="font-semibold text-zinc-800 dark:text-zinc-200">
                  {detailArticle.date.includes('2026') ? detailArticle.date.replace('Selasa, ', '').replace('Sep', 'September') : detailArticle.date}
                </span>
              </div>

              {/* ==================== 3. BAR TOMBOL AKSI (TANPA GARIS OUTLINE BORDER, SANGAT RAPI) ==================== */}
              <div className="flex flex-wrap items-center justify-between gap-3 py-1 text-xs">
                
                {/* Left group: 1. Icon Like & 2. Icon Render */}
                <div className="flex flex-wrap items-center gap-2">
                  {/* 1. Icon Like */}
                  <button
                    onClick={() => handleLike(detailArticle.id)}
                    className="flex items-center gap-1.5 px-3.5 py-2 bg-zinc-100 dark:bg-zinc-800/90 hover:bg-red-50 dark:hover:bg-red-950/40 text-zinc-800 dark:text-zinc-200 hover:text-[#CC0000] dark:hover:text-red-400 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    title="Sukai Artikel Ini"
                  >
                    <ThumbsUp className="w-4 h-4 text-[#CC0000]" />
                    <span>Suka ({detailArticle.likes})</span>
                  </button>

                  {/* 2. Icon Render */}
                  <button
                    onClick={() => selectArticleForStory(detailArticle)}
                    className="flex items-center gap-1.5 px-3.5 py-2 bg-[#CC0000] hover:bg-[#990000] text-white rounded-xl text-xs font-bold transition-all shadow-md cursor-pointer"
                    title="Render ke Template Desain Story 9:16"
                  >
                    <ImageIcon className="w-4 h-4" />
                    <span>Render Desain</span>
                  </button>
                </div>

                {/* Right group: 3. Bagikan ke Media Sosial (Official Brand SVG Icons) & 4. Icon Simpan Offline */}
                <div className="flex flex-wrap items-center gap-2.5">
                  {/* 3. Bagikan ke Media Sosial dengan Icon Asli */}
                  <div className="flex items-center gap-1.5">
                    <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider hidden sm:inline mr-1">
                      BAGIKAN:
                    </span>
                    
                    {/* WhatsApp Authentic Icon */}
                    <a
                      href={`https://api.whatsapp.com/send?text=${encodeURIComponent(detailArticle.title + ' ' + window.location.href)}`}
                      target="_blank"
                      rel="noreferrer"
                      className="w-8 h-8 rounded-full bg-[#25D366] hover:opacity-90 flex items-center justify-center text-white transition shadow-2xs"
                      title="Bagikan ke WhatsApp"
                    >
                      <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.285-.143-1.689-.834-1.95-.929-.261-.095-.451-.143-.641.143-.19.285-.736.929-.902 1.119-.166.19-.333.214-.618.071-.285-.143-1.204-.444-2.294-1.415-.848-.755-1.42-1.687-1.586-1.972-.166-.285-.018-.439.125-.581.129-.128.285-.333.428-.499.143-.166.19-.285.285-.476.095-.19.048-.356-.024-.499-.071-.143-.641-1.545-.878-2.115-.231-.555-.466-.48-.641-.489l-.547-.01c-.19 0-.499.071-.76.356-.261.285-.998.975-.998 2.378 0 1.402 1.022 2.757 1.164 2.948.143.19 2.013 3.074 4.877 4.312.681.294 1.213.47 1.627.601.684.218 1.307.187 1.799.114.549-.082 1.689-.689 1.926-1.355.237-.666.237-1.236.166-1.355-.071-.119-.261-.19-.546-.333z"/>
                      </svg>
                    </a>

                    {/* X (Twitter) Authentic Icon */}
                    <a
                      href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(detailArticle.title)}&url=${encodeURIComponent(window.location.href)}`}
                      target="_blank"
                      rel="noreferrer"
                      className="w-8 h-8 rounded-full bg-black text-white hover:opacity-80 flex items-center justify-center transition shadow-2xs"
                      title="Bagikan ke X (Twitter)"
                    >
                      <svg className="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                      </svg>
                    </a>

                    {/* Threads Official Icon */}
                    <a
                      href={`https://www.threads.net/intent/post?text=${encodeURIComponent(detailArticle.title + ' ' + window.location.href)}`}
                      target="_blank"
                      rel="noreferrer"
                      className="w-8 h-8 rounded-full bg-black text-white hover:opacity-80 flex items-center justify-center transition shadow-2xs p-1.5"
                      title="Bagikan ke Threads"
                    >
                      <img
                        src="/Threads_(app).png"
                        alt="Threads"
                        className="w-full h-full object-contain"
                      />
                    </a>

                    {/* Instagram Authentic Icon */}
                    <button
                      onClick={() => selectArticleForStory(detailArticle)}
                      className="w-8 h-8 rounded-full text-white hover:opacity-90 flex items-center justify-center transition shadow-2xs cursor-pointer"
                      style={{ background: 'radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%,#d6249f 60%,#285AEB 90%)' }}
                      title="Bagikan ke Instagram Story"
                    >
                      <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                      </svg>
                    </button>

                    {/* Facebook Authentic Icon */}
                    <a
                      href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}`}
                      target="_blank"
                      rel="noreferrer"
                      className="w-8 h-8 rounded-full bg-[#1877F2] hover:opacity-90 flex items-center justify-center text-white transition shadow-2xs"
                      title="Bagikan ke Facebook"
                    >
                      <svg className="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                      </svg>
                    </a>

                    {/* Gmail Official Multi-Color Icon */}
                    <a
                      href={`mailto:?subject=${encodeURIComponent(detailArticle.title)}&body=${encodeURIComponent(window.location.href)}`}
                      className="w-8 h-8 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:opacity-90 flex items-center justify-center transition shadow-2xs p-1.5"
                      title="Bagikan via Gmail / Email"
                    >
                      <img
                        src="/Gmail_icon_(2026).svg.webp"
                        alt="Gmail"
                        className="w-full h-full object-contain"
                      />
                    </a>

                    {/* Salin Tautan */}
                    <button
                      onClick={() => copyShareLink(detailArticle)}
                      className="px-3 py-1.5 bg-zinc-200 dark:bg-zinc-800 hover:bg-zinc-300 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 rounded-lg text-xs font-bold transition cursor-pointer"
                      title="Salin Tautan Artikel"
                    >
                      {copySuccess ? 'Tersalin!' : 'Salin Tautan'}
                    </button>
                  </div>

                  {/* 4. Icon Simpan Offline */}
                  <button
                    onClick={(e) => toggleBookmark(detailArticle.id, e)}
                    className={`flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                      bookmarkedIds.includes(detailArticle.id)
                        ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 shadow-2xs'
                        : 'bg-zinc-100 dark:bg-zinc-800/90 text-zinc-800 dark:text-zinc-200 hover:bg-zinc-200 shadow-2xs'
                    }`}
                    title="Simpan Bacaan Offline"
                  >
                    <Bookmark className={`w-4 h-4 ${bookmarkedIds.includes(detailArticle.id) ? 'fill-current text-amber-600' : ''}`} />
                    <span>{bookmarkedIds.includes(detailArticle.id) ? 'Tersimpan Offline' : 'Simpan Offline'}</span>
                  </button>
                </div>
              </div>
            </div>

            {/* ==================== 4. FOTO COVER BERITA ==================== */}
            <div className="space-y-2">
              <div className="aspect-video w-full rounded-2xl overflow-hidden bg-zinc-900 shadow-xl border border-zinc-200 dark:border-zinc-800">
                <img
                  src={detailArticle.image}
                  alt={detailArticle.title}
                  className="w-full h-full object-cover"
                />
              </div>
              <p className="text-xs text-zinc-500 dark:text-zinc-400 italic px-1">
                {detailArticle.caption || `Ilustrasi liputan ${detailArticle.title}. (Good News Nusantara)`}
              </p>
            </div>

            {/* ==================== 5. KOTA, GNN - ISI ARTIKEL ==================== */}
            {(() => {
              const city = detailArticle.location
                ? (detailArticle.location.includes(',') ? detailArticle.location.split(',')[0] : detailArticle.location)
                : 'Jakarta';
              const prefix = `<strong style="color:#CC0000;font-weight:900">${city}, GNN &ndash;&ndash; </strong>`;
              // Inject prefix inside first <p> or <h1-h6> so it's inline with the text
              const html = detailArticle.content
                ? detailArticle.content.replace(
                    /(<(?:p|h[1-6])[^>]*>)/,
                    `$1${prefix}`
                  )
                : `<p>${prefix}</p>`;
              return (
                <div
                  className="prose max-w-none text-zinc-800 dark:text-zinc-200 text-base leading-relaxed pt-2"
                  dangerouslySetInnerHTML={{ __html: html }}
                />
              );
            })()}

            {/* Back Button */}
            <div className="pt-8 text-center border-t border-zinc-200 dark:border-zinc-800">
              <button
                onClick={() => setActiveTab('home')}
                className="px-6 py-2.5 bg-zinc-900 dark:bg-zinc-800 hover:bg-zinc-800 dark:hover:bg-zinc-700 text-white text-xs font-bold uppercase tracking-wider rounded transition-colors cursor-pointer border border-transparent dark:border-zinc-700"
              >
                ← Kembali ke Halaman Utama
              </button>
            </div>

          </div>
        )}

        {/* ==================== VIEW 3: RENDER DESAIN TEMPLATE (GNN / CNN) ==================== */}
        {!loading && activeTab === 'story' && (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            {/* Left Column: Creator Customization Tools (5 cols) */}
            <div className="lg:col-span-5 space-y-5">
              <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded p-5 space-y-4 shadow-xs">
                
                <div className="border-b border-zinc-200 dark:border-zinc-800 pb-3 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <ImageIcon className="w-5 h-5 text-[#CC0000]" />
                    <h3 className="font-black text-sm uppercase tracking-wide text-zinc-950 dark:text-zinc-100">
                      Render Desain Postingan
                    </h3>
                  </div>
                  <span className="text-[10px] bg-red-100 dark:bg-red-950/80 text-[#CC0000] dark:text-red-400 font-black px-2 py-0.5 rounded">
                    Template Otomatis
                  </span>
                </div>

                {/* Model Desain Selector (Full Cover Gradient vs Split Card) */}
                <div className="space-y-1.5">
                  <label className="block text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300 flex items-center justify-between">
                    <span>Model Desain Render:</span>
                    <span className="text-[10px] text-[#CC0000] font-black">{storyLayout === 'full-cover' ? 'Full Cover' : 'Split Card'}</span>
                  </label>
                  <div className="grid grid-cols-2 gap-2">
                    <button
                      onClick={() => setStoryLayout('full-cover')}
                      className={`p-2.5 text-left border rounded transition-all cursor-pointer ${
                        storyLayout === 'full-cover'
                          ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold shadow-xs'
                          : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'
                      }`}
                    >
                      <div className="text-xs font-black">Full Cover Gradasi</div>
                      <div className="text-[9px] text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium">Foto Penuh + Teks Rata Kiri</div>
                    </button>

                    <button
                      onClick={() => setStoryLayout('gnn-split')}
                      className={`p-2.5 text-left border rounded transition-all cursor-pointer ${
                        storyLayout === 'gnn-split'
                          ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold shadow-xs'
                          : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'
                      }`}
                    >
                      <div className="text-xs font-black">Split Card</div>
                      <div className="text-[9px] text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium">Foto 2/3 & Teks Rata Tengah</div>
                    </button>
                  </div>
                </div>

                {/* Aspect Ratio Options (1:1, 4:5, 9:16) */}
                <div className="space-y-1.5">
                  <label className="block text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300 flex items-center justify-between">
                    <span>Opsi Ukuran Rasio:</span>
                    <span className="text-[10px] text-[#CC0000] font-black">{storyRatio}</span>
                  </label>
                  <div className="grid grid-cols-3 gap-2">
                    <button
                      onClick={() => setStoryRatio('1:1')}
                      className={`p-2.5 text-center border rounded transition-all cursor-pointer ${
                        storyRatio === '1:1'
                          ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold shadow-xs'
                          : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'
                      }`}
                    >
                      <div className="text-sm font-black">1:1</div>
                      <div className="text-[9px] text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium">Persegi (Feed)</div>
                    </button>

                    <button
                      onClick={() => setStoryRatio('4:5')}
                      className={`p-2.5 text-center border rounded transition-all cursor-pointer ${
                        storyRatio === '4:5'
                          ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold shadow-xs'
                          : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'
                      }`}
                    >
                      <div className="text-sm font-black">4:5</div>
                      <div className="text-[9px] text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium">Potret (Instagram)</div>
                    </button>

                    <button
                      onClick={() => setStoryRatio('9:16')}
                      className={`p-2.5 text-center border rounded transition-all cursor-pointer ${
                        storyRatio === '9:16'
                          ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold shadow-xs'
                          : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'
                      }`}
                    >
                      <div className="text-sm font-black">9:16</div>
                      <div className="text-[9px] text-zinc-500 dark:text-zinc-400 mt-0.5 font-medium">Story / WA</div>
                    </button>
                  </div>
                </div>

                {/* Font Size Selector */}
                <div className="space-y-1.5">
                  <label className="block text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300">Ukuran Teks Judul:</label>
                  <div className="grid grid-cols-3 gap-2">
                    <button
                      onClick={() => setStoryFontSize('sm')}
                      className={`py-1.5 text-center border rounded text-xs cursor-pointer ${storyFontSize === 'sm' ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'}`}
                    >
                      Kecil
                    </button>
                    <button
                      onClick={() => setStoryFontSize('md')}
                      className={`py-1.5 text-center border rounded text-xs cursor-pointer ${storyFontSize === 'md' ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'}`}
                    >
                      Sedang
                    </button>
                    <button
                      onClick={() => setStoryFontSize('lg')}
                      className={`py-1.5 text-center border rounded text-xs cursor-pointer ${storyFontSize === 'lg' ? 'border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] dark:text-red-400 font-bold' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300'}`}
                    >
                      Besar
                    </button>
                  </div>
                </div>

                {/* Kredit Gambar Input */}
                <div className="space-y-1.5">
                  <label className="block text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300">Kredit Foto / Sumber:</label>
                  <input
                    type="text"
                    value={storyCreditText}
                    onChange={(e) => setStoryCreditText(e.target.value)}
                    placeholder="Contoh: Unsplash, Antara, Tim Redaksi GNN"
                    className="w-full bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded px-3 py-1.5 text-xs font-medium text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 transition"
                  />
                </div>

                {/* Download Button */}
                <div className="pt-2">
                  <button
                    onClick={downloadStoryPNG}
                    disabled={isExportingStory}
                    className="w-full py-3 bg-[#CC0000] hover:bg-[#990000] text-white font-bold text-xs uppercase tracking-wider rounded transition-colors flex items-center justify-center gap-2 shadow-sm cursor-pointer"
                  >
                    <Download className="w-4 h-4" />
                    <span>{isExportingStory ? 'Sedang Memproses Render...' : 'Unduh Desain (PNG Resolusi Tinggi)'}</span>
                  </button>
                </div>

              </div>
            </div>

            {/* Right Column: Live Render Canvas (7 cols) */}
            <div className="lg:col-span-7 flex flex-col items-center justify-center">
              
              <div className="text-xs text-zinc-400 font-semibold mb-2 uppercase tracking-wider flex items-center gap-1.5">
                <Eye className="w-3.5 h-3.5" /> Pratinjau Render Desain ({storyRatio})
              </div>

              {/* Card Container */}
              <div
                className={`overflow-hidden rounded-xl shadow-2xl border-4 border-zinc-800 dark:border-zinc-700 bg-black select-none transition-all duration-300 ${
                  storyRatio === '1:1'
                    ? 'w-[340px] sm:w-[390px]'
                    : storyRatio === '4:5'
                    ? 'w-[320px] sm:w-[370px]'
                    : 'w-[280px] sm:w-[330px]'
                }`}
              >
                
                {/* Captured Canvas target matching user template */}
                <div
                  ref={storyPreviewRef}
                  className={`w-full overflow-hidden flex flex-col bg-black text-white relative shadow-2xl select-none ${
                    storyRatio === '1:1'
                      ? 'aspect-square'
                      : storyRatio === '4:5'
                      ? 'aspect-4/5'
                      : 'aspect-9/16'
                  }`}
                  style={{ fontFamily: "'Roboto Serif', Roboto, Georgia, serif" }}
                >
                  
                  {/* 4 ELEMEN PATEN DI 4 SUDUT FRAME */}
                  {/* 1. Kiri Atas: Logo Good News Nusantara */}
                  <div className="absolute top-0 left-4 z-30 flex items-start select-none">
                    <img src="/icon/logow.png" alt="Good News Nusantara" className="h-10 sm:h-12 w-auto object-contain drop-shadow-lg" />
                  </div>

                  {/* 2. Kanan Atas: Kategori */}
                  <div className="absolute top-4 right-6 z-30 flex items-center select-none">
                    <span className="bg-[#FF0000] text-white font-black px-2 py-0.5 rounded-xs uppercase text-[9px] tracking-wider shadow-md">
                      {storyArticle?.category || 'INSPIRASI'}
                    </span>
                  </div>

                  {/* 3. Kiri Bawah: Domain Website */}
                  <div className="absolute bottom-3 left-6 z-30 text-[10px] text-zinc-300 font-semibold tracking-wide drop-shadow-md select-none">
                    www.goodnewsnusantara.my.id
                  </div>

                  {/* 4. Kanan Bawah: Kredit Gambar (Sumber Foto) */}
                  <div className="absolute bottom-3 right-6 z-30 text-[10px] text-zinc-300 font-semibold tracking-wide drop-shadow-md text-right select-none max-w-[50%] truncate">
                    Gambar: {storyCreditText || storyArticle?.imageCredit || 'Unsplash'}
                  </div>

                  {storyLayout === 'full-cover' ? (
                    /* MODEL 1: FULL COVER (Foto Penuh, 1/3 Bawah Gradien & Judul Rata Kiri dari Atas ke Bawah) */
                    <>
                      {/* Full Photo Cover */}
                      <img
                        src={storyCustomImage || storyArticle?.image || 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80'}
                        alt=""
                        className="absolute inset-0 w-full h-full object-cover z-0"
                        crossOrigin="anonymous"
                      />

                      {/* Gradient + Judul di 1/3 Bagian Bawah Frame (Rata Kiri, Alur Atas ke Bawah) */}
                      <div
                        className="absolute inset-x-0 bottom-0 h-[38%] pt-6 pb-10 px-6 sm:px-8 flex flex-col justify-start items-start text-left z-10"
                        style={{
                          background: 'linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.8) 60%, rgba(0,0,0,0) 100%)'
                        }}
                      >
                        <div
                          contentEditable
                          suppressContentEditableWarning
                          onBlur={(e) => setStoryMainText(e.currentTarget.textContent || '')}
                          className={`font-bold text-white tracking-normal text-left w-full outline-none focus:ring-1 focus:ring-white/30 cursor-text drop-shadow-md ${
                            storyFontSize === 'sm' ? 'text-lg sm:text-xl' : storyFontSize === 'lg' ? 'text-2xl sm:text-3xl' : 'text-xl sm:text-2xl'
                          }`}
                          style={{ fontFamily: "'Roboto Serif', Roboto, Georgia, serif", lineHeight: '1.3' }}
                        >
                          {storyMainText}
                        </div>
                      </div>
                    </>
                  ) : (
                    /* MODEL 2: SPLIT CARD (Foto 2/3 ~67% di Atas, Kartu Hitam 1/3 ~33% di Bawah, Judul Rata Tengah dari Atas ke Bawah) */
                    <div className="relative w-full h-full flex flex-col">
                      {/* Area Foto (2/3 ~ 67% Height) */}
                      <div className="relative w-full h-[67%] overflow-hidden bg-zinc-900 shrink-0">
                        <img
                          src={storyCustomImage || storyArticle?.image || 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80'}
                          alt=""
                          className="w-full h-full object-cover"
                          crossOrigin="anonymous"
                        />
                      </div>

                      {/* Area Teks Hitam (1/3 ~ 33% Height) — judul rata tengah dari atas ke bawah */}
                      <div className="relative w-full h-[33%] bg-black flex flex-col justify-start items-center text-center px-6 pt-4 pb-10 z-10 overflow-hidden">
                        <div
                          contentEditable
                          suppressContentEditableWarning
                          onBlur={(e) => setStoryMainText(e.currentTarget.textContent || '')}
                          className={`font-bold text-white tracking-normal text-center w-full outline-none focus:ring-1 focus:ring-white/30 cursor-text line-clamp-3 ${
                            storyFontSize === 'sm' ? 'text-base sm:text-lg' : storyFontSize === 'lg' ? 'text-xl sm:text-2xl' : 'text-lg sm:text-xl'
                          }`}
                          style={{ fontFamily: "'Roboto Serif', Roboto, Georgia, serif", lineHeight: '1.3' }}
                        >
                          {storyMainText}
                        </div>
                      </div>
                    </div>
                  )}

                </div>

              </div>

            </div>

          </div>
        )}



        {/* ==================== VIEW: PROFIL PENULIS / SAHABAT ==================== */}
        {!loading && activeTab === 'author' && selectedAuthor && (
          <div className="max-w-5xl mx-auto space-y-8 py-6">
            <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-xl flex flex-col sm:flex-row items-center gap-6">
              <img
                src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=80"
                alt={selectedAuthor.name}
                className="w-24 h-24 rounded-full object-cover border-4 border-[#E64A19] shadow-md"
              />
              <div className="space-y-2 text-center sm:text-left">
                <span className="px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-[#E64A19] dark:bg-orange-950/60 dark:text-orange-400">
                  Sahabat Penulis GNN
                </span>
                <h1 className="text-2xl font-black text-zinc-900 dark:text-zinc-100">
                  {selectedAuthor.name}
                </h1>
                <p className="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                  URL Profil Penulis: <strong className="text-zinc-800 dark:text-zinc-200">http://localhost:3060/#/{selectedAuthor.username}</strong>
                </p>
                <p className="text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed max-w-xl">
                  Kontributor aktif Good News Nusantara yang konsisten membagikan karya tulis inspiratif, analisis positif, dan kabar baik seputar Indonesia.
                </p>
              </div>
            </div>

            {/* List of articles written by author */}
            <div className="space-y-4">
              <h2 className="text-lg font-black text-zinc-900 dark:text-zinc-100 uppercase tracking-tight border-b-2 border-[#E64A19] pb-2">
                Daftar Artikel Karya {selectedAuthor.name}
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {articles
                  .filter(
                    (a) =>
                      (a.authorEmail && a.authorEmail.split('@')[0].toLowerCase() === selectedAuthor.username) ||
                      slugify(a.author) === selectedAuthor.username ||
                      a.author.toLowerCase().includes(selectedAuthor.name.toLowerCase())
                  )
                  .map((art) => (
                    <div
                      key={art.id}
                      onClick={() => openDetail(art)}
                      className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition cursor-pointer group flex flex-col justify-between"
                    >
                      <div>
                        <div className="aspect-video relative overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                          <img
                            src={art.image}
                            alt={art.title}
                            className="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                          />
                          <span className="absolute top-3 left-3 bg-[#E64A19] text-white text-[10px] font-bold px-2 py-0.5 rounded">
                            {art.category}
                          </span>
                        </div>
                        <div className="p-4 space-y-2">
                          <h3 className="font-bold text-base text-zinc-900 dark:text-zinc-100 group-hover:text-[#E64A19] transition leading-snug">
                            {art.title}
                          </h3>
                          <p className="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2">
                            {art.content.replace(/<[^>]*>?/gm, '')}
                          </p>
                        </div>
                      </div>
                      <div className="p-4 pt-0 text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center justify-between border-t border-zinc-100 dark:border-zinc-800/80 mt-2">
                        <span>{art.date}</span>
                        <span className="text-[#E64A19] font-bold font-mono">
                          /{getAuthorUsername(art)}/{slugify(art.title)}
                        </span>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          </div>
        )}

        {/* ==================== VIEW 5: GNN TV YOUTUBE PORTAL ==================== */}
        {!loading && activeTab === 'tv' && (
          <div className="space-y-8">
            
            {/* Main YouTube Section: Active Player + Playlist Sidebar */}
            {(() => {
              const displayVideos = videos.filter((v) => {
                const matchesCategory = tvCategoryFilter === 'SEMUA' || v.category.toUpperCase() === tvCategoryFilter.toUpperCase();
                const matchesSearch = !searchQuery.trim() || 
                  v.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
                  v.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
                  v.author.toLowerCase().includes(searchQuery.toLowerCase());
                return matchesCategory && matchesSearch;
              });

              const currentActive = activeVideo || displayVideos[0] || videos[0];

              return (
                <div className="space-y-10">
                  
                  {/* Main 2-column layout (Hero Player + Related Sidebar) */}
                  <div ref={playerRef} className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    
                    {/* Left 8 columns: Large YouTube Embedded Player */}
                    <div className="lg:col-span-8 space-y-4">
                      
                      {currentActive ? (
                        <>
                          {/* YouTube Player Container */}
                          <div className="aspect-video w-full bg-black rounded-2xl overflow-hidden relative shadow-2xl border border-zinc-800">
                            <iframe
                              className="w-full h-full"
                              src={`https://www.youtube-nocookie.com/embed/${currentActive.youtubeId}?autoplay=1&rel=0`}
                              title={currentActive.title}
                              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                              allowFullScreen
                            ></iframe>
                          </div>

                          {/* Active Video Title & Channel Meta */}
                          <div className="space-y-3 pt-1">
                            <h1 className="text-lg sm:text-xl md:text-2xl font-black text-zinc-950 dark:text-zinc-100 leading-tight">
                              {currentActive.title}
                            </h1>

                            <div className="flex flex-wrap items-center justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800 text-xs">
                              {/* Author / Channel info */}
                              <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-full bg-[#CC0000] text-white flex items-center justify-center font-black text-sm uppercase shadow-sm">
                                  {currentActive.author.substring(0, 2)}
                                </div>
                                <div>
                                  <div className="font-bold text-zinc-900 dark:text-zinc-100 text-sm flex items-center gap-1.5">
                                    <span>{currentActive.author}</span>
                                    <span className="bg-red-100 dark:bg-red-950 text-[#CC0000] text-[9px] font-black uppercase px-1.5 py-0.2 rounded">
                                      REDAKSI GNN
                                    </span>
                                  </div>
                                  <div className="text-zinc-500 dark:text-zinc-400 text-[11px]">
                                    {currentActive.date} • Durasi: {currentActive.duration || '05:00'}
                                  </div>
                                </div>
                              </div>

                              {/* Action buttons */}
                              <div className="flex items-center gap-2">
                                <button
                                  onClick={() => {
                                    setTvLikedIds(prev =>
                                      prev.includes(currentActive.id)
                                        ? prev.filter(id => id !== currentActive.id)
                                        : [...prev, currentActive.id]
                                    );
                                  }}
                                  className={`flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer ${
                                    tvLikedIds.includes(currentActive.id)
                                      ? 'bg-red-100 dark:bg-red-950/80 text-[#CC0000]'
                                      : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700'
                                  }`}
                                >
                                  <ThumbsUp className={`w-4 h-4 ${tvLikedIds.includes(currentActive.id) ? 'fill-current' : ''}`} />
                                  <span>Suka {tvLikedIds.includes(currentActive.id) ? '(1)' : ''}</span>
                                </button>

                                <button
                                  onClick={() => {
                                    const url = `${window.location.origin}/#tv`;
                                    navigator.clipboard.writeText(url);
                                    setTvCopiedId(currentActive.id);
                                    setTimeout(() => setTvCopiedId(null), 2500);
                                  }}
                                  className="flex items-center gap-1.5 px-4 py-2 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 rounded-xl text-xs font-bold transition cursor-pointer"
                                >
                                  <Share2 className="w-4 h-4" />
                                  <span>{tvCopiedId === currentActive.id ? 'Tersalin!' : 'Salin Tautan'}</span>
                                </button>
                              </div>
                            </div>

                            {/* YouTube Style Description Box */}
                            <div className="bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 rounded-xl text-xs text-zinc-700 dark:text-zinc-300 space-y-2">
                              <div className="font-bold flex items-center justify-between text-zinc-900 dark:text-zinc-100">
                                <span className="uppercase tracking-wider text-[10px] bg-red-600 text-white font-extrabold px-2 py-0.5 rounded">
                                  {currentActive.category}
                                </span>
                                <span className="text-[11px] text-zinc-400 font-mono">ID: {currentActive.youtubeId}</span>
                              </div>
                              <p className="leading-relaxed whitespace-pre-line">
                                {currentActive.description}
                              </p>
                              <div className="pt-2 flex flex-wrap gap-2 text-[11px] font-semibold text-[#CC0000] dark:text-red-400">
                                <span>#GoodNewsNusantara</span>
                                <span>#GNNTV</span>
                                <span>#{currentActive.category}</span>
                                <span>#MakinTahuIndonesia</span>
                              </div>
                            </div>

                          </div>
                        </>
                      ) : (
                        <div className="p-12 text-center text-zinc-500 bg-zinc-100 dark:bg-zinc-900 rounded-2xl">
                          Tidak ada video yang ditemukan.
                        </div>
                      )}

                    </div>

                    {/* Right 4 columns: Video Playlist Sidebar */}
                    <div className="lg:col-span-4 space-y-4">
                      <div className="border-b-2 border-[#CC0000] pb-2 flex items-center justify-between">
                        <h3 className="font-black text-sm uppercase tracking-wider text-zinc-950 dark:text-zinc-100 flex items-center gap-2">
                          <Film className="w-4 h-4 text-[#CC0000]" />
                          <span>DAFTAR VIDEO GNN TV</span>
                        </h3>
                        <span className="text-[10px] text-zinc-500 font-bold">{displayVideos.length} VIDEO</span>
                      </div>

                      <div className="space-y-3 max-h-[600px] overflow-y-auto pr-1">
                        {displayVideos.map((vid) => {
                          const isPlaying = currentActive?.id === vid.id;
                          return (
                            <div
                              key={vid.id}
                              onClick={() => {
                                setActiveVideo(vid);
                                playerRef.current?.scrollIntoView({ behavior: 'smooth' });
                              }}
                              className={`group cursor-pointer p-2 rounded-xl flex gap-3 transition-all ${
                                isPlaying
                                  ? 'bg-red-50 dark:bg-red-950/60 border border-red-300 dark:border-red-900 shadow-sm'
                                  : 'hover:bg-zinc-100 dark:hover:bg-zinc-900 border border-transparent'
                              }`}
                            >
                              {/* Thumbnail with duration badge */}
                              <div className="w-36 aspect-video shrink-0 rounded-lg overflow-hidden relative bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xs">
                                <img
                                  src={`https://img.youtube.com/vi/${vid.youtubeId}/hqdefault.jpg`}
                                  alt={vid.title}
                                  className="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                />
                                {/* Play Icon Overlay */}
                                <div className="absolute inset-0 bg-black/30 group-hover:bg-black/10 flex items-center justify-center transition">
                                  <div className={`p-1.5 rounded-full ${isPlaying ? 'bg-[#CC0000] text-white' : 'bg-black/60 text-white group-hover:bg-[#CC0000]'}`}>
                                    <Play className="w-3.5 h-3.5 fill-current ml-0.5" />
                                  </div>
                                </div>
                                {/* Duration Pill */}
                                <span className="absolute bottom-1 right-1 bg-black/80 text-white text-[9px] font-bold px-1 py-0.2 rounded font-mono">
                                  {vid.duration || '04:15'}
                                </span>
                              </div>

                              {/* Video Title & Meta */}
                              <div className="flex flex-col justify-between py-0.5 min-w-0">
                                <div>
                                  {isPlaying && (
                                    <span className="text-[9px] font-black text-[#CC0000] uppercase tracking-wider block mb-0.5">
                                      • SEDANG DIPUTAR
                                    </span>
                                  )}
                                  <h4 className={`text-xs font-bold leading-snug line-clamp-2 transition-colors ${
                                    isPlaying
                                      ? 'text-[#CC0000] dark:text-red-400'
                                      : 'text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000]'
                                  }`}>
                                    {vid.title}
                                  </h4>
                                </div>
                                <div className="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">
                                  <span>{vid.author}</span>
                                  <span className="mx-1">•</span>
                                  <span>{vid.category}</span>
                                </div>
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    </div>

                  </div>

                  {/* Bottom Section: Full Youtube Video Grid Gallery */}
                  <section className="pt-8 border-t border-zinc-200 dark:border-zinc-800 space-y-6">
                    <div className="flex items-center justify-between">
                      <div>
                        <h3 className="text-lg font-black uppercase tracking-tight text-zinc-950 dark:text-zinc-100 flex items-center gap-2">
                          <Tv className="w-5 h-5 text-[#CC0000]" />
                          <span>JELAJAH KOLEKSI VIDEO GNN TV</span>
                        </h3>
                        <p className="text-xs text-zinc-500 mt-0.5">Semua tayangan dokumenter, berita video, dan liputan khusus.</p>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                      {displayVideos.map((vid) => (
                        <div
                          key={vid.id}
                          onClick={() => {
                            setActiveVideo(vid);
                            playerRef.current?.scrollIntoView({ behavior: 'smooth' });
                          }}
                          className="group cursor-pointer bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-xs hover:shadow-lg transition duration-300 flex flex-col"
                        >
                          {/* Thumbnail Card */}
                          <div className="aspect-video w-full bg-zinc-900 relative overflow-hidden">
                            <img
                              src={`https://img.youtube.com/vi/${vid.youtubeId}/hqdefault.jpg`}
                              alt={vid.title}
                              className="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                            />
                            <div className="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition flex items-center justify-center">
                              <div className="w-12 h-12 rounded-full bg-[#CC0000]/90 text-white flex items-center justify-center shadow-lg group-hover:scale-110 transition">
                                <Play className="w-5 h-5 fill-current ml-0.5" />
                              </div>
                            </div>
                            <span className="absolute top-2 left-2 bg-[#CC0000] text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-md shadow-xs">
                              {vid.category}
                            </span>
                            <span className="absolute bottom-2 right-2 bg-black/80 text-white text-[10px] font-bold px-1.5 py-0.5 rounded font-mono">
                              {vid.duration || '05:00'}
                            </span>
                          </div>

                          {/* Card Content */}
                          <div className="p-4 flex-grow flex flex-col justify-between space-y-2">
                            <h4 className="font-bold text-sm text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] dark:group-hover:text-red-400 leading-snug line-clamp-2 transition-colors">
                              {vid.title}
                            </h4>
                            <div className="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800">
                              <span className="font-semibold text-zinc-700 dark:text-zinc-300 truncate max-w-[140px]">{vid.author}</span>
                              <span className="font-mono text-zinc-400">{vid.date}</span>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </section>

                </div>
              );
            })()}

          </div>
        )}

      </main>

      {/* ============================================================== */}
      {/* 7. CLEAN & MODERN GOOD NEWS NUSANTARA FOOTER */}
      {/* ============================================================== */}
      <footer className="bg-zinc-950 text-zinc-400 text-xs border-t border-zinc-800/80 mt-16 pt-12 pb-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 space-y-10">
          
          <div className="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12 items-start">
            
            {/* Left Column (5 cols): Logo & Brand Statement */}
            <div className="md:col-span-5 space-y-3.5">
              <div className="flex items-center gap-3">
                <GnfiLogo isDarkMode={true} className="h-9 w-auto" />
                <span className="font-extrabold text-white text-base tracking-tight font-display">
                  GOOD NEWS NUSANTARA
                </span>
              </div>
              <p className="text-zinc-400 text-xs leading-relaxed max-w-sm">
                Portal berita positif & kabar baik seputar Indonesia. Membangun optimisme, kebanggaan, dan wawasan nusantara secara independen.
              </p>
            </div>

            {/* Right Columns (7 cols split into 3 clean groups) */}
            <div className="md:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-6 sm:gap-8">
              
              {/* Group 1: Kategori Berita */}
              <div className="space-y-2.5">
                <h4 className="text-white font-bold text-xs uppercase tracking-wider">Kategori</h4>
                <ul className="space-y-1.5 text-xs text-zinc-400">
                  <li><button onClick={() => { setSelectedCategory('Ekonomi'); setActiveTab('home'); }} className="hover:text-white transition-colors">Ekonomi & Bisnis</button></li>
                  <li><button onClick={() => { setSelectedCategory('Saintek'); setActiveTab('home'); }} className="hover:text-white transition-colors">Saintek & Teknologi</button></li>
                  <li><button onClick={() => { setSelectedCategory('Politik'); setActiveTab('home'); }} className="hover:text-white transition-colors">Politik & Kebijakan</button></li>
                  <li><button onClick={() => { setSelectedCategory('Sosial'); setActiveTab('home'); }} className="hover:text-white transition-colors">Sosial & Budaya</button></li>
                  <li><button onClick={() => { setSelectedCategory('Lifestyle'); setActiveTab('home'); }} className="hover:text-white transition-colors">Lifestyle & Olahraga</button></li>
                </ul>
              </div>

              {/* Group 2: Layanan & Fitur */}
              <div className="space-y-2.5">
                <h4 className="text-white font-bold text-xs uppercase tracking-wider">Layanan</h4>
                <ul className="space-y-1.5 text-xs text-zinc-400">
                  <li><button onClick={() => setActiveTab('story')} className="hover:text-white transition-colors">Story 9:16 Creator</button></li>
                  <li><button onClick={() => { if (studioUser) setActiveTab('cms'); else setActiveTab('loginStudio'); }} className="hover:text-white transition-colors">Studio Redaksi</button></li>
                  <li><a href="#" className="hover:text-white transition-colors">Pedoman Media Siber</a></li>
                  <li><a href="#" className="hover:text-white transition-colors">Tentang Kami</a></li>
                </ul>
              </div>

              {/* Group 3: Informasi & Kontak */}
              <div className="space-y-2.5">
                <h4 className="text-white font-bold text-xs uppercase tracking-wider">Informasi</h4>
                <ul className="space-y-1.5 text-xs text-zinc-400">
                  <li><a href="#" className="hover:text-white transition-colors">Kebijakan Privasi</a></li>
                  <li><a href="#" className="hover:text-white transition-colors">Ketentuan Layanan</a></li>
                  <li><a href="#" className="hover:text-white transition-colors">Kontak Iklan</a></li>
                  <li><a href="#" className="hover:text-white transition-colors">Sitemap</a></li>
                </ul>
              </div>

            </div>

          </div>

          {/* Bottom Bar */}
          <div className="pt-6 border-t border-zinc-800/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-zinc-500 text-[11px]">
            <p>© {new Date().getFullYear()} GOOD NEWS NUSANTARA (GNN). Hak Cipta Dilindungi Undang-Undang.</p>
            <p className="font-semibold text-zinc-400">Makin Tahu Indonesia</p>
          </div>

        </div>
      </footer>

      {/* ============================================================== */}
      {/* VIDEO POPUP MODAL (INTERACTIVE PLAYER) */}
      {/* ============================================================== */}
      {selectedVideoModal && (
        <div
          className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6"
          onClick={() => setSelectedVideoModal(null)}
        >
          <div
            className="bg-zinc-950 text-white rounded-2xl overflow-hidden max-w-4xl w-full border border-zinc-800 shadow-2xl relative flex flex-col"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Header bar */}
            <div className="flex items-center justify-between px-5 py-3.5 border-b border-zinc-800 bg-zinc-900/90">
              <div className="flex items-center gap-2">
                <span className="px-2 py-0.5 bg-[#CC0000] text-white text-[10px] font-black uppercase rounded">
                  {selectedVideoModal.category}
                </span>
                <span className="text-xs font-bold text-zinc-300 truncate max-w-md">
                  {selectedVideoModal.title}
                </span>
              </div>
              <button
                onClick={() => setSelectedVideoModal(null)}
                className="w-8 h-8 rounded-full bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white flex items-center justify-center transition cursor-pointer"
                title="Tutup Video"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Video Iframe Player */}
            <div className="aspect-video w-full bg-black">
              <iframe
                src={`https://www.youtube-nocookie.com/embed/${selectedVideoModal.youtubeId}?autoplay=1&rel=0`}
                title={selectedVideoModal.title}
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
                className="w-full h-full"
              />
            </div>

            {/* Video Info Details */}
            <div className="p-5 space-y-2 bg-zinc-950">
              <h3 className="text-base sm:text-lg font-extrabold text-white leading-snug">
                {selectedVideoModal.title}
              </h3>
              {selectedVideoModal.description && (
                <p className="text-xs text-zinc-400 leading-relaxed">
                  {selectedVideoModal.description}
                </p>
              )}
              <div className="text-[11px] text-zinc-500 pt-1 flex items-center gap-2">
                <span>Diproduksi oleh: <strong className="text-zinc-300">{selectedVideoModal.author}</strong></span>
                <span>•</span>
                <span>{selectedVideoModal.date}</span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Global Sahabat Registration Modal */}
      <SahabatRegisterModal
        isOpen={showRegisterModal}
        onClose={() => setShowRegisterModal(false)}
        onSuccessRegister={(newUser) => {
          setStudioUser(newUser);
          localStorage.setItem('studio_user', JSON.stringify(newUser));
          setActiveTab('cms');
        }}
      />

    </div>
  );
}
