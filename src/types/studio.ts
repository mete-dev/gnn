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
