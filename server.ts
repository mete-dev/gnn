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
  keywords?: string[];
  imageCredit?: string;
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

const INITIAL_ARTICLES: Article[] = [];

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
const INITIAL_GALLERY: GalleryItem[] = [];

// Initial Video Data
const INITIAL_VIDEOS: VideoItem[] = [];


// --- SUPABASE MAPPING & ASYNC DATA HELPERS ---

function mapDbToArticle(row: any): Article {
  return {
    id: String(row.id),
    category: row.category,
    subCategory: row.sub_category || row.subCategory || row.category,
    title: row.title,
    content: row.content,
    image: row.image,
    caption: row.caption || '',
    breaking: !!row.breaking,
    featured: !!row.featured,
    popularRank: row.popular_rank || row.popularRank,
    bullets: Array.isArray(row.bullets) ? row.bullets : [],
    date: row.date || '',
    timeAgo: row.time_ago || row.timeAgo || 'Baru saja',
    author: row.author,
    authorEmail: row.author_email || row.authorEmail || '',
    editor: row.editor || 'Redaksi GNN',
    location: row.location || 'Jakarta, Good News Nusantara',
    keywords: Array.isArray(row.keywords) ? row.keywords : [],
    imageCredit: row.image_credit || row.imageCredit || '',
    likes: row.likes || 0,
    views: row.views || 0,
    comments: Array.isArray(row.comments) ? row.comments : [],
    isPhotoGallery: !!(row.is_photo_gallery || row.isPhotoGallery),
    photoCount: row.photo_count || row.photoCount || 0,
    videoDuration: row.video_duration || row.videoDuration || '',
    status: row.status || 'publish',
    createdAt: row.created_at || row.createdAt,
    updatedAt: row.updated_at || row.updatedAt,
  };
}

function mapArticleToDb(art: Article): any {
  return {
    id: art.id,
    category: art.category,
    sub_category: art.subCategory,
    title: art.title,
    content: art.content,
    image: art.image,
    caption: art.caption,
    breaking: !!art.breaking,
    featured: !!art.featured,
    popular_rank: art.popularRank || null,
    bullets: art.bullets || [],
    date: art.date,
    time_ago: art.timeAgo,
    author: art.author,
    author_email: art.authorEmail,
    editor: art.editor,
    location: art.location,
    keywords: art.keywords || [],
    image_credit: art.imageCredit,
    likes: art.likes || 0,
    views: art.views || 0,
    comments: art.comments || [],
    is_photo_gallery: !!art.isPhotoGallery,
    photo_count: art.photoCount || 0,
    video_duration: art.videoDuration,
    status: art.status || 'publish',
    updated_at: new Date().toISOString(),
  };
}

function loadNews(): Article[] {
  if (fs.existsSync(DB_FILE)) {
    try {
      const data = fs.readFileSync(DB_FILE, 'utf-8');
      const parsed: Article[] = JSON.parse(data);
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

async function loadNewsAsync(): Promise<Article[]> {
  try {
    const { data, error } = await supabase.from('articles').select('*').order('created_at', { ascending: false });
    if (!error && Array.isArray(data)) {
      saveNews(data.map(mapDbToArticle));
      return data.map(mapDbToArticle);
    }
  } catch (e) {
    console.warn('Supabase articles fetch fallback to local DB:', e);
  }
  return loadNews();
}

async function saveNewsItemAsync(article: Article): Promise<void> {
  const current = loadNews();
  const idx = current.findIndex(a => a.id === article.id);
  let updatedList = [...current];
  if (idx !== -1) updatedList[idx] = article;
  else updatedList.unshift(article);
  saveNews(updatedList);

  try {
    const dbRow = mapArticleToDb(article);
    await supabase.from('articles').upsert(dbRow);
  } catch (e) {
    console.error('Error syncing article to Supabase:', e);
  }
}

async function deleteNewsItemAsync(id: string): Promise<void> {
  saveNews(loadNews().filter(a => a.id !== id));
  try {
    await supabase.from('articles').delete().eq('id', id);
  } catch (e) {
    console.error('Error deleting article from Supabase:', e);
  }
}

// User Sync Helpers
function mapDbToUser(row: any): User {
  return {
    id: String(row.id),
    username: row.username,
    email: row.email,
    name: row.name,
    role: row.role || 'sahabat',
    password: row.password,
    avatar: row.avatar,
    createdAt: row.created_at || row.createdAt,
    status: row.status || 'active',
  };
}

function mapUserToDb(u: User): any {
  return {
    id: u.id,
    username: u.username,
    email: u.email,
    name: u.name,
    role: u.role,
    password: u.password,
    avatar: u.avatar,
    status: u.status || 'active',
  };
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

async function loadUsersAsync(): Promise<User[]> {
  try {
    const { data, error } = await supabase.from('users').select('*');
    if (!error && Array.isArray(data) && data.length > 0) {
      saveUsers(data.map(mapDbToUser));
      return data.map(mapDbToUser);
    }
  } catch (e) {
    console.warn('Supabase users fetch fallback to local DB:', e);
  }
  return loadUsers();
}

async function saveUserAsync(user: User): Promise<void> {
  const current = loadUsers();
  const idx = current.findIndex(u => u.id === user.id);
  let updated = [...current];
  if (idx !== -1) updated[idx] = user;
  else updated.push(user);
  saveUsers(updated);

  try {
    await supabase.from('users').upsert(mapUserToDb(user));
  } catch (e) {
    console.error('Error syncing user to Supabase:', e);
  }
}

async function deleteUserAsync(id: string): Promise<void> {
  saveUsers(loadUsers().filter(u => u.id !== id));
  try {
    await supabase.from('users').delete().eq('id', id);
  } catch (e) {
    console.error('Error deleting user from Supabase:', e);
  }
}

// Gallery Sync Helpers
function mapDbToGallery(row: any): GalleryItem {
  return {
    id: String(row.id),
    title: row.title,
    imageUrl: row.image_url || row.imageUrl,
    fileSizeKb: row.file_size_kb || row.fileSizeKb || 0,
    caption: row.caption,
    author: row.author,
    authorEmail: row.author_email || row.authorEmail,
    date: row.date,
    usedCount: row.used_count || row.usedCount || 0,
  };
}

function mapGalleryToDb(g: GalleryItem): any {
  return {
    id: g.id,
    title: g.title,
    image_url: g.imageUrl,
    file_size_kb: g.fileSizeKb,
    caption: g.caption,
    author: g.author,
    author_email: g.authorEmail,
    date: g.date,
    used_count: g.usedCount || 0,
  };
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

async function loadGalleryAsync(): Promise<GalleryItem[]> {
  try {
    const { data, error } = await supabase.from('gallery').select('*');
    if (!error && Array.isArray(data)) {
      saveGallery(data.map(mapDbToGallery));
      return data.map(mapDbToGallery);
    }
  } catch (e) {
    console.warn('Supabase gallery fetch fallback to local DB:', e);
  }
  return loadGallery();
}

async function saveGalleryAsync(item: GalleryItem): Promise<void> {
  const current = loadGallery();
  const idx = current.findIndex(g => g.id === item.id);
  let updated = [...current];
  if (idx !== -1) updated[idx] = item;
  else updated.unshift(item);
  saveGallery(updated);

  try {
    await supabase.from('gallery').upsert(mapGalleryToDb(item));
  } catch (e) {
    console.error('Error syncing gallery to Supabase:', e);
  }
}

async function deleteGalleryAsync(id: string): Promise<void> {
  saveGallery(loadGallery().filter(g => g.id !== id));
  try {
    await supabase.from('gallery').delete().eq('id', id);
  } catch (e) {
    console.error('Error deleting gallery item from Supabase:', e);
  }
}

// Video Sync Helpers
function mapDbToVideo(row: any): VideoItem {
  return {
    id: String(row.id),
    title: row.title,
    youtubeUrl: row.youtube_url || row.youtubeUrl,
    youtubeId: row.youtube_id || row.youtubeId,
    description: row.description,
    category: row.category,
    subCategory: row.sub_category || row.subCategory,
    author: row.author,
    authorEmail: row.author_email || row.authorEmail,
    date: row.date,
    duration: row.duration,
  };
}

function mapVideoToDb(v: VideoItem): any {
  return {
    id: v.id,
    title: v.title,
    youtube_url: v.youtubeUrl,
    youtube_id: v.youtubeId,
    description: v.description,
    category: v.category,
    sub_category: v.subCategory,
    author: v.author,
    author_email: v.authorEmail,
    date: v.date,
    duration: v.duration,
  };
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

async function loadVideosAsync(): Promise<VideoItem[]> {
  try {
    const { data, error } = await supabase.from('videos').select('*');
    if (!error && Array.isArray(data)) {
      saveVideos(data.map(mapDbToVideo));
      return data.map(mapDbToVideo);
    }
  } catch (e) {
    console.warn('Supabase videos fetch fallback to local DB:', e);
  }
  return loadVideos();
}

async function saveVideoAsync(item: VideoItem): Promise<void> {
  const current = loadVideos();
  const idx = current.findIndex(v => v.id === item.id);
  let updated = [...current];
  if (idx !== -1) updated[idx] = item;
  else updated.unshift(item);
  saveVideos(updated);

  try {
    await supabase.from('videos').upsert(mapVideoToDb(item));
  } catch (e) {
    console.error('Error syncing video to Supabase:', e);
  }
}

async function deleteVideoAsync(id: string): Promise<void> {
  saveVideos(loadVideos().filter(v => v.id !== id));
  try {
    await supabase.from('videos').delete().eq('id', id);
  } catch (e) {
    console.error('Error deleting video item from Supabase:', e);
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
app.get('/api/news', async (req, res) => {
  let articles = await loadNewsAsync();
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
app.post('/api/news', async (req, res) => {
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

  await saveNewsItemAsync(newArticle);
  res.json({ success: true, data: newArticle });
});

// 3. Edit article
app.put('/api/news/:id', async (req, res) => {
  const articles = await loadNewsAsync();
  const { id } = req.params;
  const idx = articles.findIndex(a => a.id === id);

  if (idx === -1) {
    return res.status(404).json({ success: false, message: 'Artikel tidak ditemukan.' });
  }

  const updatedArticle: Article = { 
    ...articles[idx], 
    ...req.body, 
    id,
    updatedAt: new Date().toISOString()
  };

  await saveNewsItemAsync(updatedArticle);
  res.json({ success: true, data: updatedArticle });
});

// 4. Delete article
app.delete('/api/news/:id', async (req, res) => {
  const articles = await loadNewsAsync();
  const { id } = req.params;
  const filtered = articles.filter(a => a.id !== id);

  if (filtered.length === articles.length) {
    return res.status(404).json({ success: false, message: 'Artikel tidak ditemukan.' });
  }

  await deleteNewsItemAsync(id);
  res.json({ success: true, message: 'Artikel berhasil dihapus.' });
});

// --- AUTH & LOGIN ROUTE ---
app.post('/api/auth/login', async (req, res) => {
  const users = await loadUsersAsync();
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

app.post('/api/auth/google', async (req, res) => {
  const users = await loadUsersAsync();
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
    await saveUserAsync(user);
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
app.post('/api/auth/register-sahabat', async (req, res) => {
  const users = await loadUsersAsync();
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

  await saveUserAsync(newUser);

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
app.get('/api/users', async (req, res) => {
  const users = await loadUsersAsync();
  res.json({ success: true, data: users });
});

app.post('/api/users', async (req, res) => {
  const users = await loadUsersAsync();
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

  await saveUserAsync(newUser);
  res.json({ success: true, data: newUser });
});

app.put('/api/users/:id', async (req, res) => {
  const users = await loadUsersAsync();
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

  await saveUserAsync(updatedUser);
  res.json({ success: true, data: updatedUser });
});

app.delete('/api/users/:id', async (req, res) => {
  const users = await loadUsersAsync();
  const { id } = req.params;
  const filtered = users.filter((u) => u.id !== id);

  if (filtered.length === users.length) {
    return res.status(404).json({ success: false, message: 'Pengguna tidak ditemukan.' });
  }

  await deleteUserAsync(id);
  res.json({ success: true, message: 'Pengguna berhasil dihapus.' });
});

// --- GALLERY ROUTES ---
app.get('/api/gallery', async (req, res) => {
  const gallery = await loadGalleryAsync();
  res.json({ success: true, data: gallery });
});

app.post('/api/gallery', async (req, res) => {
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

  await saveGalleryAsync(newItem);
  res.json({ success: true, data: newItem });
});

app.delete('/api/gallery/:id', async (req, res) => {
  const { id } = req.params;
  await deleteGalleryAsync(id);
  res.json({ success: true, message: 'Gambar galeri berhasil dihapus.' });
});

// --- VIDEO ROUTES ---
app.get('/api/videos', async (req, res) => {
  const videos = await loadVideosAsync();
  res.json({ success: true, data: videos });
});

app.post('/api/videos', async (req, res) => {
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

  await saveVideoAsync(newItem);
  res.json({ success: true, data: newItem });
});

app.put('/api/videos/:id', async (req, res) => {
  const videos = await loadVideosAsync();
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

  const updatedVideo: VideoItem = {
    ...videos[idx],
    ...(title && { title }),
    ...(youtubeUrl && { youtubeUrl }),
    ...(youtubeId && { youtubeId }),
    ...(description !== undefined && { description }),
    ...(category && { category }),
    ...(subCategory && { subCategory }),
    ...(duration && { duration }),
  };

  await saveVideoAsync(updatedVideo);
  res.json({ success: true, data: updatedVideo });
});

app.delete('/api/videos/:id', async (req, res) => {
  const { id } = req.params;
  await deleteVideoAsync(id);
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
app.post('/api/news/:id/like', async (req, res) => {
  const articles = await loadNewsAsync();
  const { id } = req.params;
  const article = articles.find(a => a.id === id);

  if (!article) {
    return res.status(404).json({ success: false, message: 'Artikel tidak ditemukan.' });
  }

  article.likes = (article.likes || 0) + 1;
  await saveNewsItemAsync(article);
  res.json({ success: true, likes: article.likes });
});

// 6. Comment on article
app.post('/api/news/:id/comment', async (req, res) => {
  const articles = await loadNewsAsync();
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
  await saveNewsItemAsync(article);
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

    await saveNewsItemAsync(simulatedArticle);

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

    await saveNewsItemAsync(newArticle);

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
