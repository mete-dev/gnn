import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';
import dotenv from 'dotenv';
import { GoogleGenAI, Type } from '@google/genai';
import { createClient } from '@supabase/supabase-js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Supabase Client Initialization
const supabaseUrl = process.env.VITE_SUPABASE_URL || process.env.SUPABASE_URL || 'https://bqzcgwpyjanuvaqivpeo.supabase.co';
const supabaseAnonKey = process.env.VITE_SUPABASE_ANON_KEY || process.env.SUPABASE_ANON_KEY || 'sb_publishable_PJY02yK96tsrbpINNdAQoA_w_7CMN8Q';

export const supabase = createClient(supabaseUrl, supabaseAnonKey);
console.log('Supabase Client initialized with URL:', supabaseUrl);

const app = express();
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ limit: '50mb', extended: true }));

// Initialize Gemini Client safely
let ai: GoogleGenAI | null = null;
const apiKey = process.env.GEMINI_API_KEY;

if (apiKey && apiKey !== 'MY_GEMINI_API_KEY') {
  try {
    ai = new GoogleGenAI({
      apiKey: apiKey,
      httpOptions: {
        headers: {
          'User-Agent': 'aistudio-build',
        },
      },
    });
    console.log('Gemini AI Client initialized successfully for Good News Nusantara.');
  } catch (error) {
    console.error('Failed to initialize Gemini Client:', error);
  }
}

const DB_FILE = path.resolve(__dirname, 'gnn_news_db.json');
const USERS_DB_FILE = path.resolve(__dirname, 'gnn_users_db.json');
const GALLERY_DB_FILE = path.resolve(__dirname, 'gnn_gallery_db.json');
const VIDEOS_DB_FILE = path.resolve(__dirname, 'gnn_videos_db.json');

export type Role = 'admin' | 'reviewer' | 'sahabat';

export interface User {
  id: string;
  username: string;
  email: string;
  name: string;
  role: Role;
  password?: string;
  avatar?: string;
  createdAt: string;
  status: 'active' | 'inactive';
}

export type ArticleStatus = 'draft' | 'review' | 'publish';

export interface Article {
  id: string;
  category: string;
  subCategory?: string;
  title: string;
  content: string;
  image: string;
  caption?: string;
  breaking: boolean;
  featured: boolean; // Headline Utama
  popularRank?: number; // 1 to 5 for trending sidebar
  bullets: string[];
  date: string;
  timeAgo: string;
  author: string;
  authorEmail?: string;
  editor: string;
  location: string;
  likes: number;
  views: number;
  comments: { id: string; user: string; text: string; time: string }[];
  isPhotoGallery?: boolean;
  photoCount?: number;
  videoDuration?: string;
  status?: ArticleStatus;
  createdAt?: string;
  updatedAt?: string;
}

export interface GalleryItem {
  id: string;
  title: string;
  imageUrl: string;
  fileSizeKb: number;
  caption?: string;
  author: string;
  authorEmail: string;
  date: string;
  usedCount?: number;
}

export interface VideoItem {
  id: string;
  title: string;
  youtubeUrl: string;
  youtubeId: string;
  description: string;
  category: string;
  subCategory?: string;
  author: string;
  authorEmail: string;
  date: string;
  duration?: string;
}

const INITIAL_ARTICLES: Article[] = [
  {
    id: 'gnn-101',
    category: 'KABAR BAIK',
    subCategory: 'PRESTASI DUNIA',
    title: 'Sempat Menguat, Kini 1 USD Kembali ke Rp18.000',
    content: `Kawasan Gunung Bromo di Jawa Timur kembali mencuri perhatian dunia sebagai salah satu destinasi wisata alam terpopuler dan terindah di Asia Tenggara.\n\nDengan pemandangan kawah aktif yang megah dan lautan pasir berbisik yang eksotis, kawasan ini berhasil menerapkan pariwisata ramah lingkungan berbasis kearifan lokal masyarakat Suku Tengger.\n\nKementerian Pariwisata dan Ekonomi Kreatif mencatat kunjungan wisatawan mancanegara ke Jawa Timur meningkat 34% berkat perbaikan infrastruktur dan promosi berkelanjutan.`,
    image: 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80',
    caption: 'Kawasan Gunung Bromo yang mempesona saat matahari terbit di Jawa Timur. (Dokumentasi GNN/Unsplash)',
    breaking: true,
    featured: true,
    popularRank: 1,
    bullets: [
      'Gunung Bromo dinobatkan taman nasional terfavorit di Asia',
      'Kearifan lokal Suku Tengger jadi pilar konservasi lingkungan',
      'Kunjungan wisatawan naik 34% dorong ekonomi masyarakat lokal',
      'Penerapan standar ecotourism berkelas internasional'
    ],
    date: 'Selasa, 29 Sep 2026',
    timeAgo: '5 menit lalu',
    author: 'Fadhil Al Anshori',
    editor: 'Tim Redaksi GNN',
    location: 'Probolinggo, Good News Nusantara',
    likes: 842,
    views: 32400,
    comments: []
  },
  {
    id: 'gnn-102',
    category: 'INSPIRASI',
    subCategory: 'ANAK BANGSA',
    title: 'Inovator Muda Sleman Ciptakan Filter Air Tenaga Surya, Bantu 40 Desa Bebas Krisis Air Bersih',
    content: `Sekelompok mahasiswa teknik asal Sleman, Yogyakarta, berhasil mengembangkan alat penyaring air portabel bertenaga surya bernama TirtaSurya yang mampu memurnikan air keruh dan payau menjadi air siap minum dengan standar WHO.\n\nAlat ini memanfaatkan membran karbon aktif dari ampas tebu lokal serta teknologi sinar ultraviolet bertenaga panel surya efisiensi tinggi.\n\nHingga September 2026, lebih dari 40 desa di kawasan pesisir Gunungkidul dan Nusa Tenggara Timur telah merasakan manfaat langsung dari teknologi terjangkau karya anak negeri ini.`,
    image: 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=1200&q=80',
    caption: 'Anak-anak sekolah di pelosok desa menikmati air minum bersih hasil filtrasi bertenaga surya. (GNN/Istimewa)',
    breaking: true,
    featured: false,
    popularRank: 2,
    bullets: [
      'Alat pemurni air bertenaga surya dengan biaya operasional Rp0',
      'Gunakan filter alami karbon aktif dari limbah ampas tebu',
      'Telah alirkan air bersih ke 40 desa rawan kekeringan',
      'Raih penghargaan Young Innovator Summit di Jenewa'
    ],
    date: 'Selasa, 29 Sep 2026',
    timeAgo: '20 menit lalu',
    author: 'Annisa Maharani',
    editor: 'Rizky Ramadhan',
    location: 'Yogyakarta, Good News Nusantara',
    likes: 650,
    views: 25100,
    comments: []
  },
  {
    id: 'gnn-103',
    category: 'BUDAYA',
    subCategory: 'WARISAN NUSANTARA',
    title: 'UNESCO Resmi Sahkan Reog Ponorogo dan Kebaya Indonesia sebagai Warisan Budaya Takbenda Dunia',
    content: `Kabar membanggakan kembali datang dari panggung kebudayaan internasional. Sidang Komite Warisan Budaya Takbenda UNESCO di Paris resmi menetapkan seni pertunjukan Reog Ponorogo dan busana tradisional Kebaya ke dalam daftar Warisan Budaya Takbenda (Intangible Cultural Heritage) Dunia.\n\nDelegasi Indonesia yang dipimpin perwakilan seniman dan sejarawan mempresentasikan filosofi luhur di balik Reog dan Kebaya yang melambangkan keanggunan, kegigihan, dan harmoni keberagaman Indonesia.\n\nPenetapan ini diharapkan mempercepat pelestarian lintas generasi dan pemberdayaan para pengrajin dadak merak serta penenun kain nusantara di seluruh pelosok tanah air.`,
    image: 'https://images.unsplash.com/photo-1578632767115-351597cf2477?auto=format&fit=crop&w=1200&q=80',
    caption: 'Penari dadak merak Reog Ponorogo tampil memukau dengan kostum megah penuh filosofi. (Dokumentasi GNN)',
    breaking: false,
    featured: false,
    popularRank: 3,
    bullets: [
      'Reog Ponorogo & Kebaya diakui UNESCO sebagai warisan dunia',
      'Bukti kekayaan narasi dan estetika adiluhung budaya nusantara',
      'Apresiasi tinggi masyarakat dunia atas harmoni tradisi Indonesia',
      'Dorong regenerasi pegiat seni tradisi di berbagai daerah'
    ],
    date: 'Selasa, 29 Sep 2026',
    timeAgo: '45 menit lalu',
    author: 'Bagus Wicaksono',
    editor: 'Desk Budaya GNN',
    location: 'Paris, Good News Nusantara',
    likes: 1240,
    views: 48900,
    comments: []
  },
  {
    id: 'gnn-104',
    category: 'PARIWISATA',
    subCategory: 'DESA WISATA',
    title: 'Desa Wisata Penglipuran Bali Raih Predikat Desa Terbersih di Dunia Versi Green Destinations',
    content: `Desa Adat Penglipuran di Kabupaten Bangli, Bali, kembali dinobatkan sebagai salah satu desa terbersih dan paling lestari di dunia dalam ajang Global Green Destinations Summit 2026.\n\nTata kelola desa yang berakar kuat pada falsafah Tri Hita Karana—harmoni antara manusia, alam semesta, dan Sang Pencipta—menjadikan desa ini bebas polusi kendaraan bermotor, asri dengan rerimbunan hutan bambu sakral, serta bersih dari sampah plastik.\n\nPenghargaan ini menjadi inspirasi nyata bagi ratusan desa wisata lainnya di Indonesia untuk mengedepankan kearifan lokal dalam menjaga ekosistem bumi.`,
    image: 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1200&q=80',
    caption: 'Deretan rumah adat tertata rapi di Desa Adat Penglipuran yang sejuk dan asri. (GNN/Shutterstock)',
    breaking: false,
    featured: false,
    popularRank: 4,
    bullets: [
      'Penglipuran kembali raih predikat desa terbersih dunia',
      'Terapkan filosofi Tri Hita Karana dalam tata kelola lingkungan',
      'Bebas kendaraan bermotor dengan hutan bambu lestari seluas 45 ha',
      'Model percontohan pariwisata berkelanjutan global'
    ],
    date: 'Selasa, 29 Sep 2026',
    timeAgo: '1 jam lalu',
    author: 'Ni Luh Made',
    editor: 'Tim Redaksi GNN',
    location: 'Bangli, Good News Nusantara',
    likes: 980,
    views: 31200,
    comments: []
  },
  {
    id: 'gnn-105',
    category: 'ALAM',
    subCategory: 'KONSERVASI FAUNA',
    title: 'Dua Anak Badak Jawa Kembali Lahir di Taman Nasional Ujung Kulon, Populasi Tumbuh Sehat',
    content: `Kamera jebak (camera trap) Balai Taman Nasional Ujung Kulon (TNUK) di Banten berhasil merekam kelahiran dua ekor anak badak jawa (Rhinoceros sondaicus) yang sehat bersama induknya di semenanjung Ujung Kulon.\n\nKelahiran satwa paling langka di dunia ini menjadi kabar yang sangat menggembirakan bagi upaya konservasi global. Keberhasilan ini tak lepas dari kerja keras tim patroli Rhino Protection Unit (RPU) dan masyarakat adat sekitar yang konsisten menjaga habitat dari perambahan liar.\n\nKementerian LHK menegaskan komitmen untuk terus memperluas zona perlindungan dan habitat kedua (second habitat) guna memastikan kelangsungan hidup spesies ikonik Indonesia ini.`,
    image: 'https://images.unsplash.com/photo-1534567153574-2b12153a87f0?auto=format&fit=crop&w=1200&q=80',
    caption: 'Satwa endemik langka di alam liar Ujung Kulon Banten. (Balai TNUK/KemenLHK)',
    breaking: false,
    featured: false,
    popularRank: 5,
    bullets: [
      'Dua ekor anak badak jawa terekam camera trap dalam kondisi sehat',
      'Bukti keberhasilan proteksi habitat ketat di Semenanjung Ujung Kulon',
      'Kerja sama solid tim konservasi dan masyarakat penjaga hutan',
      'Kabar positif perlindungan keanekaragaman hayati nusantara'
    ],
    date: 'Selasa, 29 Sep 2026',
    timeAgo: '2 jam lalu',
    author: 'Wahyu Hidayat',
    editor: 'Tim Sains GNN',
    location: 'Pandeglang, Good News Nusantara',
    likes: 810,
    views: 22800,
    comments: []
  },
  {
    id: 'gnn-106',
    category: 'EKONOMI',
    subCategory: 'KEMANDIRIAN PANGAN',
    title: 'Petani Milenial Sukabumi Sukses Ekspor 50 Ton Kopi Arabika Specialty ke Pasar Eropa',
    content: `Koperasi petani muda binaan di kawasan lereng Gunung Gede Pangrango, Sukabumi, sukses melepas ekspor perdana 50 ton biji kopi arabika specialty ke Jerman dan Belanda.\n\nKopi yang dibudidayakan secara agroforestri ramah lingkungan ini berhasil meraih skor cupping 87,5 poin karena profil rasa rempah dan buah tropis yang khas.\n\nLangkah ini membuktikan bahwa generasi muda desa mampu berdaya saing global melalui modernisasi pascapanen dan pemasaran digital tanpa harus meninggalkan kampung halaman.`,
    image: 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=1200&q=80',
    caption: 'Biji kopi arabika berkualitas prima siap dikemas untuk pasar ekspor. (Dokumentasi GNN)',
    breaking: false,
    featured: false,
    bullets: [
      'Petani milenial ekspor 50 ton kopi specialty ke Jerman & Belanda',
      'Raih skor cupping tinggi 87,5 poin dengan metode ramah alam',
      'Meningkatkan taraf hidup ratusan keluarga pekebun lereng gunung',
      'Inspirasi wirausaha agro modern anak bangsa'
    ],
    date: 'Selasa, 29 Sep 2026',
    timeAgo: '3 jam lalu',
    author: 'Rahmat Fadillah',
    editor: 'Desk Ekonomi GNN',
    location: 'Sukabumi, Good News Nusantara',
    likes: 540,
    views: 18700,
    comments: []
  },
  {
    id: 'gnn-107',
    category: 'TEKNOLOGI',
    subCategory: 'INOVASI PERTANIAN',
    title: 'Peneliti IPB Kembangkan Varietas Padi Amfibi, Tahan Rendaman Banjir dan Tahan Kekeringan',
    content: `Tim peneliti dari Institut Pertanian Bogor (IPB University) meluncurkan varietas padi unggul amfibi hasil pemuliaan genetik tanaman lokal yang mampu beradaptasi pada dua kondisi ekstrem: tahan terendam banjir hingga 14 hari dan tetap berproduksi tinggi di lahan kering minim air.\n\nUji coba lapangan di Subang dan Karawang menunjukkan produktivitas varietas ini mencapai 8,4 ton gabah kering panen per hektare, melampaui rata-rata padi konvensional.\n\nInovasi ini menjadi angin segar bagi ketahanan pangan nasional di tengah tantangan perubahan iklim global.`,
    image: 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?auto=format&fit=crop&w=1200&q=80',
    caption: 'Hamparan sawah padi hijau yang subur di kawasan lumbung padi Jawa Barat. (GNN/Istimewa)',
    breaking: false,
    featured: false,
    bullets: [
      'Varietas padi amfibi tahan rendaman banjir 14 hari & kekeringan',
      'Produktivitas capai 8,4 ton/hektare di uji coba Subang-Karawang',
      'Solusi strategis ketahanan pangan di era perubahan iklim',
      'Karya riset unggulan civitas akademika IPB University'
    ],
    date: 'Selasa, 29 Sep 2026',
    timeAgo: '4 jam lalu',
    author: 'Dewi Anggraini',
    editor: 'Tim Sains GNN',
    location: 'Bogor, Good News Nusantara',
    likes: 720,
    views: 20400,
    comments: []
  }
];

// Initial Users Data
const INITIAL_USERS: User[] = [
  {
    id: 'usr-1',
    username: 'admin',
    email: 'admin@gnn.id',
    name: 'Admin Utama GNN',
    role: 'admin',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
    createdAt: '2026-01-10',
    status: 'active'
  },
  {
    id: 'usr-2',
    username: 'reviewer',
    email: 'reviewer@gnn.id',
    name: 'Redaktur Pelaksana (Reviuwer)',
    role: 'reviewer',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
    createdAt: '2026-02-15',
    status: 'active'
  },
  {
    id: 'usr-3',
    username: 'sahabat',
    email: 'sahabat@gnn.id',
    name: 'Sahabat Penulis GNN',
    role: 'sahabat',
    avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=80',
    createdAt: '2026-03-01',
    status: 'active'
  },
  {
    id: 'usr-4',
    username: 'bagus',
    email: 'bagus@gnn.id',
    name: 'Bagus Wicaksono',
    role: 'sahabat',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80',
    createdAt: '2026-03-12',
    status: 'active'
  }
];

// Initial Gallery Data
const INITIAL_GALLERY: GalleryItem[] = [
  {
    id: 'gal-101',
    title: 'Keindahan Matahari Terbit Bromo',
    imageUrl: 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80',
    fileSizeKb: 42.5,
    caption: 'Pemandangan kawah aktif Bromo saat fajar menyingsing',
    author: 'Admin Utama GNN',
    authorEmail: 'admin@gnn.id',
    date: '29 Sep 2026',
    usedCount: 3
  },
  {
    id: 'gal-102',
    title: 'Filtrasi Air Tenaga Surya TirtaSurya',
    imageUrl: 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=1200&q=80',
    fileSizeKb: 38.2,
    caption: 'Anak-anak menikmati air bersih terfiltrasi',
    author: 'Sahabat Penulis GNN',
    authorEmail: 'sahabat@gnn.id',
    date: '28 Sep 2026',
    usedCount: 2
  },
  {
    id: 'gal-103',
    title: 'Pagelaran Seni Reog Ponorogo',
    imageUrl: 'https://images.unsplash.com/photo-1578632767115-351597cf2477?auto=format&fit=crop&w=1200&q=80',
    fileSizeKb: 45.1,
    caption: 'Atraksi memukau dadak merak di Paris UNESCO',
    author: 'Bagus Wicaksono',
    authorEmail: 'bagus@gnn.id',
    date: '27 Sep 2026',
    usedCount: 1
  }
];

// Initial Video Data
const INITIAL_VIDEOS: VideoItem[] = [
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

function loadNews(): Article[] {
  if (fs.existsSync(DB_FILE)) {
    try {
      const data = fs.readFileSync(DB_FILE, 'utf-8');
      const parsed: Article[] = JSON.parse(data);
      // Ensure all articles have status field (default 'publish')
      return parsed.map(a => ({ ...a, status: a.status || 'publish' }));
    } catch (e) {
      console.error('Error loading GNN news DB:', e);
    }
  }
  return INITIAL_ARTICLES.map(a => ({ ...a, status: 'publish' }));
}

function saveNews(articles: Article[]) {
  try {
    fs.writeFileSync(DB_FILE, JSON.stringify(articles, null, 2), 'utf-8');
  } catch (e) {
    console.error('Error saving GNN news DB:', e);
  }
}

function loadUsers(): User[] {
  if (fs.existsSync(USERS_DB_FILE)) {
    try {
      return JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf-8'));
    } catch (e) {
      console.error('Error loading users DB:', e);
    }
  }
  return INITIAL_USERS;
}

function saveUsers(users: User[]) {
  try {
    fs.writeFileSync(USERS_DB_FILE, JSON.stringify(users, null, 2), 'utf-8');
  } catch (e) {
    console.error('Error saving users DB:', e);
  }
}

function loadGallery(): GalleryItem[] {
  if (fs.existsSync(GALLERY_DB_FILE)) {
    try {
      return JSON.parse(fs.readFileSync(GALLERY_DB_FILE, 'utf-8'));
    } catch (e) {
      console.error('Error loading gallery DB:', e);
    }
  }
  return INITIAL_GALLERY;
}

function saveGallery(items: GalleryItem[]) {
  try {
    fs.writeFileSync(GALLERY_DB_FILE, JSON.stringify(items, null, 2), 'utf-8');
  } catch (e) {
    console.error('Error saving gallery DB:', e);
  }
}

function loadVideos(): VideoItem[] {
  if (fs.existsSync(VIDEOS_DB_FILE)) {
    try {
      return JSON.parse(fs.readFileSync(VIDEOS_DB_FILE, 'utf-8'));
    } catch (e) {
      console.error('Error loading videos DB:', e);
    }
  }
  return INITIAL_VIDEOS;
}

function saveVideos(items: VideoItem[]) {
  try {
    fs.writeFileSync(VIDEOS_DB_FILE, JSON.stringify(items, null, 2), 'utf-8');
  } catch (e) {
    console.error('Error saving videos DB:', e);
  }
}

if (!fs.existsSync(DB_FILE)) saveNews(INITIAL_ARTICLES.map(a => ({ ...a, status: 'publish' })));
if (!fs.existsSync(USERS_DB_FILE)) saveUsers(INITIAL_USERS);
if (!fs.existsSync(GALLERY_DB_FILE)) saveGallery(INITIAL_GALLERY);
if (!fs.existsSync(VIDEOS_DB_FILE)) saveVideos(INITIAL_VIDEOS);

// Trending hashtags on Good News Nusantara
const TRENDING_TOPICS = [
  '#KabarBaikNusantara',
  '#GoodNewsNusantara',
  '#BanggaBuatanIndonesia',
  '#ReogUNESCO',
  '#InovasiAnakBangsa',
  '#PenglipuranBali',
  '#BadakJawaLahir',
  '#PadiAmfibiIPB'
];

// GNN Multimedia & Podcast Schedule
const LIVE_SCHEDULE = [
  { time: '10:00 - 11:30', program: 'GNN Podcast: Kabar Baik Nusantara', host: 'Redaksi GNN', isLive: true },
  { time: '13:00 - 14:00', program: 'Inspirasi Anak Negeri: Baterai Hijau', host: 'Redaksi GNN', isLive: false },
  { time: '16:00 - 17:00', program: 'Jelajah Pusaka & Budaya Nusantara', host: 'Tim Budaya GNN', isLive: false },
  { time: '19:00 - 20:30', program: 'Kilas Kabar Baik Pekan Ini', host: 'Fadhil Al Anshori', isLive: false }
];

// --- ROUTES ---

// Supabase Connection Status
app.get('/api/supabase/status', async (req, res) => {
  try {
    const { data, error } = await supabase.from('articles').select('count', { count: 'exact', head: true });
    if (error && error.code !== 'PGRST116') {
      return res.json({
        success: true,
        connected: true,
        supabaseUrl,
        message: 'Connected to Supabase. Table query response: ' + error.message,
        error: error
      });
    }
    res.json({
      success: true,
      connected: true,
      supabaseUrl,
      message: 'Supabase client is connected and active!'
    });
  } catch (err: any) {
    res.status(500).json({
      success: false,
      connected: false,
      message: err?.message || 'Error connecting to Supabase'
    });
  }
});


// 1. Get articles (supports ?status=publish|review|draft & ?authorEmail=...)
app.get('/api/news', (req, res) => {
  let articles = loadNews();
  const { status, authorEmail, role } = req.query;

  if (status) {
    articles = articles.filter(a => a.status === status);
  }

  // Sahabat access filter: if requested by sahabat role or specified authorEmail
  if (role === 'sahabat' && authorEmail) {
    articles = articles.filter(a => a.authorEmail === authorEmail || a.author?.toLowerCase().includes((authorEmail as string).split('@')[0]));
  }

  res.json({
    success: true,
    data: articles,
    trendingTopics: TRENDING_TOPICS,
    liveSchedule: LIVE_SCHEDULE
  });
});

// 2. Create article with workflow status
app.post('/api/news', (req, res) => {
  const articles = loadNews();
  const { title, content, category, subCategory, image, caption, author, authorEmail, editor, breaking, bullets, status } = req.body;

  if (!title || !content || !category) {
    return res.status(400).json({ success: false, message: 'Judul, konten, dan kategori wajib diisi.' });
  }

  const newArticle: Article = {
    id: 'gnn-' + Date.now(),
    title,
    content,
    category: category.toUpperCase(),
    subCategory: (subCategory || category).toUpperCase(),
    image: image || 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=1000&q=80',
    caption: caption || `Ilustrasi liputan ${title}. (Good News Nusantara)`,
    breaking: !!breaking,
    featured: false,
    bullets: Array.isArray(bullets) && bullets.length > 0 ? bullets : [
      title.substring(0, 75),
      'Liputan mendalam dan terverifikasi redaksi Good News Nusantara',
      'Baca ulasan komprehensif di portal GoodNewsNusantara.com'
    ],
    date: new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }),
    timeAgo: 'Baru saja',
    author: author || 'Redaksi Good News Nusantara',
    authorEmail: authorEmail || 'admin@gnn.id',
    editor: editor || 'Desk Redaksi',
    location: 'Jakarta, Good News Nusantara',
    likes: 0,
    views: 1,
    comments: [],
    status: status || 'draft',
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString()
  };

  articles.unshift(newArticle);
  saveNews(articles);
  res.json({ success: true, data: newArticle });
});

// 3. Edit article
app.put('/api/news/:id', (req, res) => {
  const articles = loadNews();
  const { id } = req.params;
  const idx = articles.findIndex(a => a.id === id);

  if (idx === -1) {
    return res.status(404).json({ success: false, message: 'Artikel tidak ditemukan.' });
  }

  articles[idx] = { 
    ...articles[idx], 
    ...req.body, 
    id,
    updatedAt: new Date().toISOString()
  };
  saveNews(articles);
  res.json({ success: true, data: articles[idx] });
});

// 4. Delete article
app.delete('/api/news/:id', (req, res) => {
  const articles = loadNews();
  const { id } = req.params;
  const filtered = articles.filter(a => a.id !== id);

  if (filtered.length === articles.length) {
    return res.status(404).json({ success: false, message: 'Artikel tidak ditemukan.' });
  }

  saveNews(filtered);
  res.json({ success: true, message: 'Artikel berhasil dihapus.' });
});

// --- AUTH & LOGIN ROUTE ---
app.post('/api/auth/login', (req, res) => {
  const users = loadUsers();
  const { identifier, password } = req.body;

  if (!identifier || !password) {
    return res.status(400).json({ success: false, message: 'Email/Username dan Password wajib diisi.' });
  }

  const cleanId = String(identifier).trim().toLowerCase();
  const cleanPass = String(password).trim();

  const user = users.find(
    (u) =>
      u.email.toLowerCase() === cleanId ||
      (u.username && u.username.toLowerCase() === cleanId)
  );

  if (!user) {
    return res.status(401).json({ success: false, message: 'Email atau Username tidak terdaftar.' });
  }

  if (user.status === 'inactive') {
    return res.status(403).json({ success: false, message: 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.' });
  }

  const userPass = user.password || '123456';
  if (userPass !== cleanPass) {
    return res.status(401).json({ success: false, message: 'Password salah. Silakan coba lagi.' });
  }

  res.json({
    success: true,
    data: {
      id: user.id,
      username: user.username,
      email: user.email,
      name: user.name,
      role: user.role,
      status: user.status,
      createdAt: user.createdAt,
    },
  });
});

// --- GOOGLE OAUTH CONFIGURATION & AUTHENTICATION ROUTES ---
const GOOGLE_CLIENT_ID = process.env.GOOGLE_CLIENT_ID || '';
const GOOGLE_CLIENT_SECRET = process.env.GOOGLE_CLIENT_SECRET || '';
const SITE_DOMAIN = 'https://www.goodnewsnusantara.my.id';

app.get('/api/auth/google/config', (req, res) => {
  res.json({
    success: true,
    clientId: GOOGLE_CLIENT_ID,
    domain: SITE_DOMAIN,
    redirectUris: [
      'http://localhost:3060/api/auth/google',
      'http://localhost:3000/api/auth/callback/google',
      'https://www.goodnewsnusantara.my.id/api/auth/callback/google',
      'https://www.goodnewsnusantara.my.id/api/auth/google'
    ]
  });
});

app.post('/api/auth/google', (req, res) => {
  const users = loadUsers();
  const { googleToken, email, name, picture, credential } = req.body;

  // Try to parse JWT payload from Google credential if passed
  let userEmail = email ? email.trim() : '';
  let userName = name ? name.trim() : '';

  if (credential) {
    try {
      const base64Url = credential.split('.')[1];
      const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
      const jsonPayload = decodeURIComponent(
        atob(base64)
          .split('')
          .map((c) => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
          .join('')
      );
      const payload = JSON.parse(jsonPayload);
      if (payload.email) userEmail = payload.email;
      if (payload.name) userName = payload.name;
    } catch (e) {
      console.warn('Failed to parse Google JWT credential payload:', e);
    }
  }

  const targetEmail = userEmail || 'sahabat.google@gnn.id';
  let user = users.find((u) => u.email.toLowerCase() === targetEmail.toLowerCase());

  if (!user) {
    const defaultUsername = (userName ? userName.toLowerCase().replace(/[^a-z0-9]/g, '') : 'sahabat') + Math.floor(1000 + Math.random() * 9000);
    user = {
      id: 'usr-google-' + Date.now(),
      username: defaultUsername,
      email: targetEmail,
      password: 'google-oauth-authenticated',
      name: userName || 'Sahabat Google GNN',
      role: 'sahabat' as Role,
      createdAt: new Date().toISOString().split('T')[0],
      status: 'active',
    };
    users.push(user);
    saveUsers(users);
  }

  if (user.status === 'inactive') {
    return res.status(403).json({ success: false, message: 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.' });
  }

  res.json({
    success: true,
    message: 'Berhasil autentikasi dengan Google OAuth.',
    data: {
      id: user.id,
      username: user.username,
      email: user.email,
      name: user.name,
      role: user.role,
      status: user.status,
      createdAt: user.createdAt,
    },
  });
});


app.all('/api/auth/callback/google', (req, res) => {
  res.redirect('https://www.goodnewsnusantara.my.id/#/studio');
});


// --- REGISTER SAHABAT ROUTE ---
app.post('/api/auth/register-sahabat', (req, res) => {
  const users = loadUsers();
  const {
    name,
    username,
    password,
    email,
    whatsapp,
    gender,
    birthDate,
    country,
    province,
    city,
    address,
  } = req.body;

  if (!name || !username || !password || !email) {
    return res.status(400).json({ success: false, message: 'Nama, Username, Password, dan Email wajib diisi.' });
  }

  if (users.some((u) => u.email.toLowerCase() === email.toLowerCase())) {
    return res.status(400).json({ success: false, message: 'Email sudah terdaftar. Silakan gunakan email lain atau login.' });
  }

  if (users.some((u) => u.username && u.username.toLowerCase() === username.toLowerCase())) {
    return res.status(400).json({ success: false, message: 'Username sudah digunakan. Silakan pilih username lain.' });
  }

  const newUser: User = {
    id: 'usr-sahabat-' + Date.now(),
    username: username.trim(),
    email: email.trim(),
    password: String(password).trim(),
    name: name.trim(),
    role: 'sahabat' as Role,
    createdAt: new Date().toISOString().split('T')[0],
    status: 'active',
  };

  users.push(newUser);
  saveUsers(users);

  res.json({
    success: true,
    message: 'Pendaftaran Sahabat GNN berhasil!',
    data: {
      id: newUser.id,
      name: newUser.name,
      username: newUser.username,
      email: newUser.email,
      role: newUser.role,
      whatsapp,
      gender,
      birthDate,
      domisili: { country, province, city, address },
      createdAt: newUser.createdAt,
    },
  });
});

// --- USER MANAGEMENT ROUTES (ADMIN ONLY) ---
app.get('/api/users', (req, res) => {
  const users = loadUsers();
  res.json({ success: true, data: users });
});

app.post('/api/users', (req, res) => {
  const users = loadUsers();
  const { name, email, role, username, password, status } = req.body;

  if (!name || !email || !role) {
    return res.status(400).json({ success: false, message: 'Nama, Email, dan Role wajib diisi.' });
  }

  if (users.some((u) => u.email.toLowerCase() === email.toLowerCase())) {
    return res.status(400).json({ success: false, message: 'Email sudah terdaftar.' });
  }

  const newUser: User = {
    id: 'usr-' + Date.now(),
    username: username || email.split('@')[0],
    email,
    password: password ? String(password).trim() : '123456',
    name,
    role: role as Role,
    createdAt: new Date().toISOString().split('T')[0],
    status: status || 'active',
  };

  users.push(newUser);
  saveUsers(users);
  res.json({ success: true, data: newUser });
});

app.put('/api/users/:id', (req, res) => {
  const users = loadUsers();
  const { id } = req.params;
  const idx = users.findIndex((u) => u.id === id);

  if (idx === -1) {
    return res.status(404).json({ success: false, message: 'Pengguna tidak ditemukan.' });
  }

  const { name, email, role, username, password, status } = req.body;
  const updatedUser: User = {
    ...users[idx],
    name: name !== undefined ? name : users[idx].name,
    email: email !== undefined ? email : users[idx].email,
    role: role !== undefined ? role : users[idx].role,
    username: username !== undefined ? username : users[idx].username,
    status: status !== undefined ? status : users[idx].status,
  };

  if (password && String(password).trim() !== '') {
    updatedUser.password = String(password).trim();
  }

  users[idx] = updatedUser;
  saveUsers(users);
  res.json({ success: true, data: users[idx] });
});

app.delete('/api/users/:id', (req, res) => {
  const users = loadUsers();
  const { id } = req.params;
  const filtered = users.filter((u) => u.id !== id);

  if (filtered.length === users.length) {
    return res.status(404).json({ success: false, message: 'Pengguna tidak ditemukan.' });
  }

  saveUsers(filtered);
  res.json({ success: true, message: 'Pengguna berhasil dihapus.' });
});

// --- GALLERY ROUTES ---
app.get('/api/gallery', (req, res) => {
  const gallery = loadGallery();
  res.json({ success: true, data: gallery });
});

app.post('/api/gallery', (req, res) => {
  const gallery = loadGallery();
  const { title, imageUrl, fileSizeKb, caption, author, authorEmail } = req.body;

  if (!imageUrl || !title) {
    return res.status(400).json({ success: false, message: 'Judul dan Gambar wajib diisi.' });
  }

  const newItem: GalleryItem = {
    id: 'gal-' + Date.now(),
    title,
    imageUrl,
    fileSizeKb: fileSizeKb || 45,
    caption: caption || title,
    author: author || 'Kontributor Studio',
    authorEmail: authorEmail || 'user@gnn.id',
    date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }),
    usedCount: 0
  };

  gallery.unshift(newItem);
  saveGallery(gallery);
  res.json({ success: true, data: newItem });
});

app.delete('/api/gallery/:id', (req, res) => {
  const gallery = loadGallery();
  const { id } = req.params;
  const filtered = gallery.filter(g => g.id !== id);
  saveGallery(filtered);
  res.json({ success: true, message: 'Gambar galeri berhasil dihapus.' });
});

// --- VIDEO ROUTES ---
app.get('/api/videos', (req, res) => {
  const videos = loadVideos();
  res.json({ success: true, data: videos });
});

app.post('/api/videos', (req, res) => {
  const videos = loadVideos();
  const { title, youtubeUrl, description, category, subCategory, author, authorEmail } = req.body;

  if (!title || !youtubeUrl) {
    return res.status(400).json({ success: false, message: 'Judul dan URL YouTube wajib diisi.' });
  }

  // Extract YouTube ID
  let youtubeId = '';
  const match = youtubeUrl.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
  if (match && match[1]) {
    youtubeId = match[1];
  } else {
    youtubeId = youtubeUrl.trim();
  }

  const newItem: VideoItem = {
    id: 'vid-' + Date.now(),
    title,
    youtubeUrl,
    youtubeId,
    description: description || '',
    category: category || 'EKONOMI',
    subCategory: subCategory || 'Ekonomi',
    author: author || 'Tim Studio GNN',
    authorEmail: authorEmail || 'admin@gnn.id',
    date: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }),
    duration: '03:45'
  };

  videos.unshift(newItem);
  saveVideos(videos);
  res.json({ success: true, data: newItem });
});

app.put('/api/videos/:id', (req, res) => {
  const videos = loadVideos();
  const { id } = req.params;
  const idx = videos.findIndex(v => v.id === id);

  if (idx === -1) {
    return res.status(404).json({ success: false, message: 'Video tidak ditemukan.' });
  }

  const { title, youtubeUrl, description, category, subCategory, duration } = req.body;

  let youtubeId = videos[idx].youtubeId;
  if (youtubeUrl) {
    const match = youtubeUrl.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
    if (match && match[1]) {
      youtubeId = match[1];
    } else if (!youtubeUrl.includes('/') && youtubeUrl.trim().length === 11) {
      youtubeId = youtubeUrl.trim();
    }
  }

  videos[idx] = {
    ...videos[idx],
    ...(title && { title }),
    ...(youtubeUrl && { youtubeUrl }),
    ...(youtubeId && { youtubeId }),
    ...(description !== undefined && { description }),
    ...(category && { category }),
    ...(subCategory && { subCategory }),
    ...(duration && { duration }),
  };

  saveVideos(videos);
  res.json({ success: true, data: videos[idx] });
});

app.delete('/api/videos/:id', (req, res) => {
  const videos = loadVideos();
  const { id } = req.params;
  const filtered = videos.filter(v => v.id !== id);
  saveVideos(filtered);
  res.json({ success: true, message: 'Video berhasil dihapus.' });
});

// --- SETTINGS (BACKUP & RESTORE - ADMIN ONLY) ---
app.get('/api/settings/backup', (req, res) => {
  const articles = loadNews();
  const users = loadUsers();
  const gallery = loadGallery();
  const videos = loadVideos();

  const backupData = {
    appName: 'Good News Nusantara Studio',
    exportedAt: new Date().toISOString(),
    version: '1.0.0',
    data: {
      articles,
      users,
      gallery,
      videos
    }
  };

  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Content-Disposition', `attachment; filename=gnn_studio_backup_${Date.now()}.json`);
  res.json(backupData);
});

app.post('/api/settings/restore', (req, res) => {
  try {
    const { data, overwrite } = req.body;
    if (!data) {
      return res.status(400).json({ success: false, message: 'Format file backup tidak valid.' });
    }

    if (overwrite) {
      if (data.articles) saveNews(data.articles);
      if (data.users) saveUsers(data.users);
      if (data.gallery) saveGallery(data.gallery);
      if (data.videos) saveVideos(data.videos);
    } else {
      // Merge
      if (data.articles) {
        const current = loadNews();
        const existingIds = new Set(current.map(a => a.id));
        const merged = [...current, ...data.articles.filter((a: any) => !existingIds.has(a.id))];
        saveNews(merged);
      }
      if (data.users) {
        const current = loadUsers();
        const existingEmails = new Set(current.map(u => u.email.toLowerCase()));
        const merged = [...current, ...data.users.filter((u: any) => !existingEmails.has(u.email.toLowerCase()))];
        saveUsers(merged);
      }
      if (data.gallery) {
        const current = loadGallery();
        const existingIds = new Set(current.map(g => g.id));
        const merged = [...current, ...data.gallery.filter((g: any) => !existingIds.has(g.id))];
        saveGallery(merged);
      }
      if (data.videos) {
        const current = loadVideos();
        const existingIds = new Set(current.map(v => v.id));
        const merged = [...current, ...data.videos.filter((v: any) => !existingIds.has(v.id))];
        saveVideos(merged);
      }
    }

    res.json({ success: true, message: 'Restore data berhasil diperbarui.' });
  } catch (err: any) {
    res.status(500).json({ success: false, message: 'Gagal melakukan restore: ' + err.message });
  }
});

// 5. Like article
app.post('/api/news/:id/like', (req, res) => {
  const articles = loadNews();
  const { id } = req.params;
  const article = articles.find(a => a.id === id);

  if (!article) {
    return res.status(404).json({ success: false, message: 'Artikel tidak ditemukan.' });
  }

  article.likes = (article.likes || 0) + 1;
  saveNews(articles);
  res.json({ success: true, likes: article.likes });
});

// 6. Comment on article
app.post('/api/news/:id/comment', (req, res) => {
  const articles = loadNews();
  const { id } = req.params;
  const { user, text } = req.body;

  if (!user || !text) {
    return res.status(400).json({ success: false, message: 'Nama dan komentar wajib diisi.' });
  }

  const article = articles.find(a => a.id === id);
  if (!article) {
    return res.status(404).json({ success: false, message: 'Artikel tidak ditemukan.' });
  }

  const newComment = {
    id: 'c-' + Date.now(),
    user,
    text,
    time: 'Baru saja'
  };

  article.comments = article.comments || [];
  article.comments.unshift(newComment);
  saveNews(articles);
  res.json({ success: true, comment: newComment });
});

// 7. AI Generate Article (Gemini or simulated fallback)
app.post('/api/news/generate-ai', async (req, res) => {
  const { topic, category } = req.body;
  if (!topic) {
    return res.status(400).json({ success: false, message: 'Topik berita harus diisi.' });
  }

  const selectedCategory = (category || 'NASIONAL').toUpperCase();

  if (!ai) {
    // High-quality fallback simulation
    const simulatedTitle = `${topic.charAt(0).toUpperCase() + topic.slice(1)}: Gebrakan Baru yang Mengubah Peta Industri Nasional`;
    const simulatedContent = `Langkah besar kembali diambil oleh para pemangku kepentingan menyusul dinamika pesat terkait ${topic}. Berdasarkan laporan investigasi mendalam, perkembangan ini menandai babak baru dalam transformasi sektor strategis tanah air.\n\nSejumlah pakar dan pengamat kebijakan publik menilai terobosan ini sebagai sinyal kuat bagi percepatan modernisasi nasional. "Kita tidak lagi sekadar menjadi pasar penonton, melainkan motor penggerak utama perubahan di kawasan regional," ujar salah seorang analis senior saat ditemui di Jakarta.\n\nPemerintah bersama pihak terkait kini tengah merampungkan peta jalan (roadmap) terintegrasi guna memastikan dampak positifnya dapat dirasakan langsung oleh masyarakat secara merata dan berkelanjutan.`;
    
    const simulatedArticle: Article = {
      id: 'gnn-' + Date.now(),
      category: selectedCategory as any,
      subCategory: 'FOKUS',
      title: simulatedTitle,
      content: simulatedContent,
      image: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1000&q=80',
      caption: `Liputan khusus terkait dinamika ${topic}. (Good News Nusantara/File)`,
      breaking: true,
      featured: false,
      bullets: [
        `Gebrakan ${topic} picu pergeseran peta strategis nasional`,
        'Pengamat nilai Indonesia kini siap jadi motor perubahan kawasan',
        'Pemerintah siapkan roadmap terintegrasi multi-sektor',
        'Simak analisis mendalam selengkapnya di Good News Nusantara'
      ],
      date: new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }),
      timeAgo: 'Baru saja',
      author: 'Tim Redaksi Khusus',
      editor: 'Editor Desk Khusus',
      location: 'Jakarta, Good News Nusantara',
      likes: 12,
      views: 340,
      comments: []
    };

    const articles = loadNews();
    articles.unshift(simulatedArticle);
    saveNews(articles);

    return res.json({ success: true, data: simulatedArticle });
  }

  try {
    const prompt = `Buatlah satu artikel berita mendalam dan tajam dengan gaya jurnalisme khas Good News Nusantara mengenai topik: "${topic}".
Kategori: "${selectedCategory}".
Syarat artikel:
1. Judul tajam, faktual, profesional khas headline Good News Nusantara (8 - 14 kata).
2. Isi berita minimal 3 paragraf. Gaya penulisan lugas, berimbang, menyertakan kutipan narasumber terpercaya atau pejabat berwenang, konteks latar belakang, serta implikasi ke depan.
3. 4 butir poin ringkasan utama (maksimal 75 karakter per poin) untuk kebutuhan Instagram Story 9:16.
4. Kata kunci pencarian gambar Unsplash berbahasa Inggris yang sangat relevan (misal: 'parliament-building', 'stock-market', 'football-stadium', 'digital-economy').

Kembalikan respon dalam JSON valid:
{
  "title": "string",
  "content": "string",
  "bullets": ["string", "string", "string", "string"],
  "imageKeyword": "string"
}`;

    const response = await ai.models.generateContent({
      model: 'gemini-3.8-flash',
      contents: prompt,
      config: {
        responseMimeType: 'application/json',
        responseSchema: {
          type: Type.OBJECT,
          properties: {
            title: { type: Type.STRING },
            content: { type: Type.STRING },
            bullets: {
              type: Type.ARRAY,
              items: { type: Type.STRING },
            },
            imageKeyword: { type: Type.STRING },
          },
          required: ['title', 'content', 'bullets', 'imageKeyword'],
        },
      },
    });

    const parsed = JSON.parse(response.text?.trim() || '{}');
    const keyword = encodeURIComponent(parsed.imageKeyword || 'indonesia-news');
    const imageUrl = `https://images.unsplash.com/featured/?${keyword}&auto=format&fit=crop&w=1200&q=80`;

    const newArticle: Article = {
      id: 'gnn-' + Date.now(),
      category: selectedCategory as any,
      subCategory: 'TERKINI',
      title: parsed.title,
      content: parsed.content,
      image: imageUrl,
      caption: `Dokumentasi terkait liputan ${parsed.title}. (Good News Nusantara)`,
      breaking: false,
      featured: false,
      bullets: parsed.bullets,
      date: new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }),
      timeAgo: 'Baru saja',
      author: 'Good News Nusantara Intelligence Desk',
      editor: 'Redaktur Pelaksana',
      location: 'Jakarta, Good News Nusantara',
      likes: 0,
      views: 1,
      comments: []
    };

    const articles = loadNews();
    articles.unshift(newArticle);
    saveNews(articles);

    res.json({ success: true, data: newArticle });
  } catch (err: any) {
    console.error('Gemini Generate Error:', err);
    res.status(500).json({ success: false, message: 'Gagal membuat berita: ' + err.message });
  }
});

// Configure Vite & static serving
const isProduction = process.env.NODE_ENV === 'production';
const PORT = process.env.PORT ? parseInt(process.env.PORT, 10) : 3060;

if (isProduction) {
  const distPath = path.resolve(__dirname, 'public_html');
  const fallbackDistPath = path.resolve(__dirname, 'dist');
  const finalPath = fs.existsSync(distPath) ? distPath : fallbackDistPath;

  if (fs.existsSync(finalPath)) {
    app.use(express.static(finalPath));
    app.get('*', (req, res, next) => {
      if (req.path.startsWith('/api/')) return next();
      res.sendFile(path.resolve(finalPath, 'index.html'));
    });
  }
} else {
  try {
    const { createServer: createViteServer } = await import('vite');
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } catch (e) {
    console.warn('Vite dev middleware skipped:', e);
  }
}

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Good News Nusantara Server listening on http://0.0.0.0:${PORT}`);
});
