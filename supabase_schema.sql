-- ==========================================================
-- Good News Nusantara (GNN) - Supabase Database Schema & Seed
-- Copy & Run this SQL in your Supabase SQL Editor:
-- https://supabase.com/dashboard/project/bqzcgwpyjanuvaqivpeo/sql
-- ==========================================================

-- 1. Table: articles (Kabar / Berita)
CREATE TABLE IF NOT EXISTS public.articles (
    id TEXT PRIMARY KEY,
    category TEXT NOT NULL,
    sub_category TEXT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    image TEXT,
    caption TEXT,
    breaking BOOLEAN DEFAULT FALSE,
    featured BOOLEAN DEFAULT FALSE,
    popular_rank INT,
    bullets JSONB DEFAULT '[]'::jsonb,
    date TEXT,
    time_ago TEXT,
    author TEXT NOT NULL,
    author_email TEXT,
    editor TEXT,
    location TEXT,
    keywords JSONB DEFAULT '[]'::jsonb,
    image_credit TEXT,
    likes INT DEFAULT 0,
    views INT DEFAULT 0,
    comments JSONB DEFAULT '[]'::jsonb,
    is_photo_gallery BOOLEAN DEFAULT FALSE,
    photo_count INT DEFAULT 0,
    video_duration TEXT,
    status TEXT DEFAULT 'publish',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Ensure all columns exist in public.articles (if table existed before)
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS category TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS sub_category TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS content TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS image TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS caption TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS breaking BOOLEAN DEFAULT FALSE;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS featured BOOLEAN DEFAULT FALSE;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS popular_rank INT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS bullets JSONB DEFAULT '[]'::jsonb;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS date TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS time_ago TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS author TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS author_email TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS editor TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS location TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS keywords JSONB DEFAULT '[]'::jsonb;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS image_credit TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS likes INT DEFAULT 0;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS views INT DEFAULT 0;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS comments JSONB DEFAULT '[]'::jsonb;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS is_photo_gallery BOOLEAN DEFAULT FALSE;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS photo_count INT DEFAULT 0;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS video_duration TEXT;
ALTER TABLE public.articles ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'publish';

-- 2. Table: users (Pengguna Studio & Redaksi)
CREATE TABLE IF NOT EXISTS public.users (
    id TEXT PRIMARY KEY,
    username TEXT,
    email TEXT,
    name TEXT,
    role TEXT DEFAULT 'sahabat',
    password TEXT,
    avatar TEXT,
    status TEXT DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Ensure all columns exist in public.users (in case table already existed)
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS username TEXT;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS name TEXT;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS role TEXT DEFAULT 'sahabat';
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS password TEXT;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS avatar TEXT;
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';
ALTER TABLE public.users ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

-- 3. Table: gallery (Galeri Foto)
CREATE TABLE IF NOT EXISTS public.gallery (
    id TEXT PRIMARY KEY,
    title TEXT,
    image_url TEXT,
    file_size_kb NUMERIC DEFAULT 0,
    caption TEXT,
    author TEXT,
    author_email TEXT,
    date TEXT,
    used_count INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS image_url TEXT;
ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS file_size_kb NUMERIC DEFAULT 0;
ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS caption TEXT;
ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS author TEXT;
ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS author_email TEXT;
ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS date TEXT;
ALTER TABLE public.gallery ADD COLUMN IF NOT EXISTS used_count INT DEFAULT 0;

-- 4. Table: videos (GNN TV / Video)
CREATE TABLE IF NOT EXISTS public.videos (
    id TEXT PRIMARY KEY,
    title TEXT,
    youtube_url TEXT,
    youtube_id TEXT,
    description TEXT,
    category TEXT,
    sub_category TEXT,
    author TEXT,
    author_email TEXT,
    date TEXT,
    duration TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS youtube_url TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS youtube_id TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS category TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS sub_category TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS author TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS author_email TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS date TEXT;
ALTER TABLE public.videos ADD COLUMN IF NOT EXISTS duration TEXT;

-- Row Level Security (RLS) & Public Policies
ALTER TABLE public.articles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.gallery ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.videos ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Public access for articles" ON public.articles;
CREATE POLICY "Public access for articles" ON public.articles FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Public access for users" ON public.users;
CREATE POLICY "Public access for users" ON public.users FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Public access for gallery" ON public.gallery;
CREATE POLICY "Public access for gallery" ON public.gallery FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Public access for videos" ON public.videos;
CREATE POLICY "Public access for videos" ON public.videos FOR ALL USING (true) WITH CHECK (true);

-- ==========================================================
-- SEED DATA (Data Awal GNN)
-- ==========================================================

INSERT INTO public.users (id, username, email, name, role, password, avatar, status) VALUES
('usr-1', 'admin', 'admin@gnn.id', 'Admin Utama GNN', 'admin', '123456', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80', 'active'),
('usr-2', 'reviewer', 'reviewer@gnn.id', 'Redaktur Pelaksana (Reviuwer)', 'reviewer', '123456', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80', 'active'),
('usr-3', 'sahabat', 'sahabat@gnn.id', 'Sahabat Penulis GNN', 'sahabat', '123456', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=80', 'active'),
('usr-4', 'bagus', 'bagus@gnn.id', 'Bagus Wicaksono', 'sahabat', '123456', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80', 'active')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.articles (id, category, sub_category, title, content, image, caption, breaking, featured, popular_rank, bullets, date, time_ago, author, editor, location, likes, views, status) VALUES
('gnn-101', 'KABAR BAIK', 'PRESTASI DUNIA', 'Sempat Menguat, Kini 1 USD Kembali ke Rp18.000', 'Kawasan Gunung Bromo di Jawa Timur kembali mencuri perhatian dunia sebagai salah satu destinasi wisata alam terpopuler dan terindah di Asia Tenggara.', 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80', 'Kawasan Gunung Bromo yang mempesona saat matahari terbit di Jawa Timur.', true, true, 1, '["Gunung Bromo dinobatkan taman nasional terfavorit di Asia", "Kearifan lokal Suku Tengger jadi pilar konservasi lingkungan"]'::jsonb, 'Selasa, 29 Sep 2026', '5 menit lalu', 'Fadhil Al Anshori', 'Tim Redaksi GNN', 'Probolinggo, Good News Nusantara', 842, 32400, 'publish')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.videos (id, title, youtube_url, youtube_id, description, category, author, author_email, date, duration) VALUES
('vid-101', 'Keindahan Alam Indonesia dari Udara | Good News Nusantara', 'https://www.youtube.com/watch?v=w80-jEaF5o0', 'w80-jEaF5o0', 'Dokumenter pendek keindahan bentang alam nusantara Gunung Bromo, Bali, dan pantai pesisir eksotis Indonesia.', 'PARIWISATA', 'Redaktur Pelaksana (Reviuwer)', 'reviewer@gnn.id', '29 Sep 2026', '04:15')
ON CONFLICT (id) DO NOTHING;
