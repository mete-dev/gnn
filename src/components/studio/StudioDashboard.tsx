import React, { useState, useEffect } from 'react';
import {
  FileText,
  Image as ImageIcon,
  Video,
  Users,
  Sliders,
  Shield,
  UserCheck,
  ChevronRight,
  ChevronLeft,
  LogOut,
  ExternalLink,
  Sparkles,
  Layers,
  ArrowLeft,
  LayoutDashboard,
  Menu,
  X,
  Plus,
  PanelLeftClose,
  PanelLeftOpen
} from 'lucide-react';
import { Article, ArticleStatus, GalleryItem, Role, User, VideoItem } from '../../types/studio';
import { ArticlesManager } from './ArticlesManager';
import { GalleryManager } from './GalleryManager';
import { VideosManager } from './VideosManager';
import { UsersManager } from './UsersManager';
import { SettingsManager } from './SettingsManager';
import { GnfiLogo } from '../GnfiLogo';

interface StudioDashboardProps {
  currentUser: User;
  onSwitchUserRole: (newRole: Role) => void;
  onBackToPortal: () => void;
  onLogout: () => void;
}

export type StudioTab = 'artikel' | 'buat-artikel' | 'galeri' | 'video' | 'pengguna' | 'pengaturan';

export const StudioDashboard: React.FC<StudioDashboardProps> = ({
  currentUser,
  onSwitchUserRole,
  onBackToPortal,
  onLogout,
}) => {
  // Deep-link route hash mapping: #/studio/artikel, #/studio/buat-artikel, #/studio/galeri, etc.
  const [activeTab, setActiveTab] = useState<StudioTab>(() => {
    const hash = window.location.hash;
    if (hash.includes('/buat-artikel')) return 'buat-artikel';
    if (hash.includes('/galeri')) return 'galeri';
    if (hash.includes('/video')) return 'video';
    if (hash.includes('/pengguna') && currentUser.role === 'admin') return 'pengguna';
    if (hash.includes('/pengaturan') && currentUser.role === 'admin') return 'pengaturan';
    return 'artikel';
  });

  const [mobileSidebarOpen, setMobileSidebarOpen] = useState<boolean>(false);
  const [isCollapsed, setIsCollapsed] = useState<boolean>(false);
  const [editingArticleData, setEditingArticleData] = useState<Partial<Article> | null>(null);

  const handleTabChange = (tab: StudioTab) => {
    if ((tab === 'pengguna' || tab === 'pengaturan') && currentUser.role !== 'admin') {
      setActiveTab('artikel');
      window.location.hash = '#/studio/artikel';
      return;
    }
    setActiveTab(tab);
    window.location.hash = `#/studio/${tab}`;
    setMobileSidebarOpen(false);
  };

  useEffect(() => {
    const handleHashChange = () => {
      const hash = window.location.hash;
      if (hash.includes('/buat-artikel')) setActiveTab('buat-artikel');
      else if (hash.includes('/galeri')) setActiveTab('galeri');
      else if (hash.includes('/video')) setActiveTab('video');
      else if (hash.includes('/pengguna') && currentUser.role === 'admin') setActiveTab('pengguna');
      else if (hash.includes('/pengaturan') && currentUser.role === 'admin') setActiveTab('pengaturan');
      else setActiveTab('artikel');
    };
    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, [currentUser.role]);

  // State data
  const [articles, setArticles] = useState<Article[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [galleryItems, setGalleryItems] = useState<GalleryItem[]>([]);
  const [videoItems, setVideoItems] = useState<VideoItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  // Fetch initial backend data
  const fetchAllData = async () => {
    setLoading(true);
    try {
      const [artRes, usrRes, galRes, vidRes] = await Promise.all([
        fetch('/api/news').then((r) => r.json()),
        fetch('/api/users').then((r) => r.json()),
        fetch('/api/gallery').then((r) => r.json()),
        fetch('/api/videos').then((r) => r.json()),
      ]);

      if (artRes.success) setArticles(artRes.data || []);
      if (usrRes.success) setUsers(usrRes.data || []);
      if (galRes.success) setGalleryItems(galRes.data || []);
      if (vidRes.success) setVideoItems(vidRes.data || []);
    } catch (e) {
      console.error('Failed to load studio data:', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAllData();
  }, []);

  // Handlers for Articles
  const handleSaveArticle = async (articleData: Partial<Article>) => {
    const isEdit = !!articleData.id;
    const url = isEdit ? `/api/news/${articleData.id}` : '/api/news';
    const method = isEdit ? 'PUT' : 'POST';

    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...articleData,
        author: articleData.author || currentUser.name,
        authorEmail: articleData.authorEmail || currentUser.email,
      }),
    }).then((r) => r.json());

    if (res.success) {
      fetchAllData();
    } else {
      throw new Error(res.message);
    }
  };

  const handleDeleteArticle = async (id: string) => {
    if (!confirm('Yakin ingin menghapus artikel ini?')) return;
    const res = await fetch(`/api/news/${id}`, { method: 'DELETE' }).then((r) =>
      r.json()
    );
    if (res.success) fetchAllData();
  };

  const handleStatusChange = async (id: string, newStatus: ArticleStatus) => {
    const res = await fetch(`/api/news/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: newStatus }),
    }).then((r) => r.json());

    if (res.success) fetchAllData();
  };

  // Handlers for Gallery
  const handleUploadImage = async (item: Partial<GalleryItem>) => {
    const res = await fetch('/api/gallery', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(item),
    }).then((r) => r.json());

    if (res.success) fetchAllData();
    else throw new Error(res.message);
  };

  const handleDeleteGalleryItem = async (id: string) => {
    if (!confirm('Hapus gambar ini dari galeri?')) return;
    const res = await fetch(`/api/gallery/${id}`, { method: 'DELETE' }).then((r) =>
      r.json()
    );
    if (res.success) fetchAllData();
  };

  // Handlers for Videos
  const handleAddVideo = async (item: Partial<VideoItem>) => {
    const res = await fetch('/api/videos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(item),
    }).then((r) => r.json());

    if (res.success) fetchAllData();
    else throw new Error(res.message);
  };

  const handleUpdateVideo = async (id: string, item: Partial<VideoItem>) => {
    const res = await fetch(`/api/videos/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(item),
    }).then((r) => r.json());

    if (res.success) fetchAllData();
    else throw new Error(res.message);
  };

  const handleDeleteVideo = async (id: string) => {
    if (!confirm('Hapus video ini?')) return;
    const res = await fetch(`/api/videos/${id}`, { method: 'DELETE' }).then((r) =>
      r.json()
    );
    if (res.success) fetchAllData();
  };

  // Handlers for User Management (Admin Only)
  const handleAddUser = async (userData: Partial<User>) => {
    const res = await fetch('/api/users', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(userData),
    }).then((r) => r.json());

    if (res.success) fetchAllData();
    else throw new Error(res.message);
  };

  const handleUpdateUser = async (id: string, userData: Partial<User>) => {
    const res = await fetch(`/api/users/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(userData),
    }).then((r) => r.json());

    if (res.success) fetchAllData();
  };

  const handleDeleteUser = async (id: string) => {
    if (!confirm('Hapus akun pengguna ini?')) return;
    const res = await fetch(`/api/users/${id}`, { method: 'DELETE' }).then((r) =>
      r.json()
    );
    if (res.success) fetchAllData();
  };

  // Handlers for Backup & Restore
  const handleBackup = () => {
    window.open('/api/settings/backup', '_blank');
  };

  const handleRestore = async (jsonData: any, overwrite: boolean) => {
    let apiSuccess = false;
    try {
      const res = await fetch('/api/settings/restore', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data: jsonData, overwrite }),
      });

      const contentType = res.headers.get('content-type') || '';
      if (res.ok && contentType.includes('application/json')) {
        const data = await res.json();
        if (data.success) {
          apiSuccess = true;
          fetchAllData();
          return;
        }
      }
    } catch (apiErr) {
      console.warn('API restore endpoint returned non-JSON response, using client fallback:', apiErr);
    }

    // Client-side fallback if cPanel proxy limit / HTML response occurs
    if (jsonData) {
      if (jsonData.articles && Array.isArray(jsonData.articles)) {
        if (overwrite) {
          setArticles(jsonData.articles);
        } else {
          setArticles((prev) => {
            const existingIds = new Set(prev.map((a) => a.id));
            return [...prev, ...jsonData.articles.filter((a: any) => !existingIds.has(a.id))];
          });
        }
      }

      if (jsonData.gallery && Array.isArray(jsonData.gallery)) {
        if (overwrite) {
          setGalleryItems(jsonData.gallery);
        } else {
          setGalleryItems((prev) => {
            const existingIds = new Set(prev.map((g) => g.id));
            return [...prev, ...jsonData.gallery.filter((g: any) => !existingIds.has(g.id))];
          });
        }
      }

      if (jsonData.videos && Array.isArray(jsonData.videos)) {
        if (overwrite) {
          setVideoItems(jsonData.videos);
        } else {
          setVideoItems((prev) => {
            const existingIds = new Set(prev.map((v) => v.id));
            return [...prev, ...jsonData.videos.filter((v: any) => !existingIds.has(v.id))];
          });
        }
      }

      if (jsonData.users && Array.isArray(jsonData.users)) {
        if (overwrite) {
          setUsers(jsonData.users);
        } else {
          setUsers((prev) => {
            const existingEmails = new Set(prev.map((u) => u.email.toLowerCase()));
            return [...prev, ...jsonData.users.filter((u: any) => !existingEmails.has(u.email.toLowerCase()))];
          });
        }
      }
    }
  };

  const NAV_ITEMS = [
    { id: 'artikel', label: 'Artikel', icon: FileText, roleAllowed: ['admin', 'reviewer', 'sahabat'] },
    { id: 'galeri', label: 'Galeri Foto', icon: ImageIcon, roleAllowed: ['admin', 'reviewer', 'sahabat'] },
    { id: 'video', label: 'Video YouTube', icon: Video, roleAllowed: ['admin', 'reviewer', 'sahabat'] },
    { id: 'pengguna', label: 'Pengguna', icon: Users, roleAllowed: ['admin'] },
    { id: 'pengaturan', label: 'Pengaturan', icon: Sliders, roleAllowed: ['admin'] },
  ];

  return (
    <div className="min-h-screen bg-zinc-50 text-zinc-900 flex font-sans antialiased">
      {/* ============================================================== */}
      {/* 1. COLLAPSIBLE LEFT SIDEBAR NAVIGATION */}
      {/* ============================================================== */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 bg-white border-r border-zinc-200 flex flex-col justify-between transition-all duration-300 md:translate-x-0 ${
          isCollapsed ? 'w-16' : 'w-52'
        } ${
          mobileSidebarOpen ? 'translate-x-0 shadow-2xl w-52' : '-translate-x-full md:translate-x-0'
        }`}
      >
        <div className="flex flex-col h-full">
          {/* Top Brand Header & Toggle Button */}
          <div className="h-[57px] px-3.5 border-b border-zinc-200 flex items-start justify-between shrink-0">
            {!isCollapsed ? (
              <div className="flex items-start h-full overflow-hidden p-0 m-0">
                <GnfiLogo isDarkMode={false} className="h-full w-auto shrink-0 object-top object-left m-0 p-0" />
              </div>
            ) : (
              <button
                onClick={() => setIsCollapsed(false)}
                className="mx-auto select-none flex items-center justify-center p-1 rounded-xl hover:bg-zinc-100 transition cursor-pointer"
                title="Buka Sidebar"
              >
                <img
                  src="/favicon.png"
                  alt="GNN Favicon"
                  className="w-7 h-7 object-contain rounded-md"
                  onError={(e) => {
                    const target = e.target as HTMLImageElement;
                    if (!target.src.includes('/favicon.ico')) {
                      target.src = '/favicon.ico';
                    }
                  }}
                />
              </button>
            )}

            {/* Desktop Tutup Toggle Button (Hanya tampil saat terbuka) */}
            {!isCollapsed && (
              <div className="flex items-center h-full">
                <button
                  onClick={() => setIsCollapsed(true)}
                  className="hidden md:flex p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition shrink-0 ml-1 cursor-pointer"
                  title="Tutup Sidebar"
                >
                  <ChevronLeft className="w-4 h-4" />
                </button>
              </div>
            )}

            {/* Mobile Close Button */}
            <div className="flex items-center h-full md:hidden">
              <button
                onClick={() => setMobileSidebarOpen(false)}
                className="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Navigation Links List */}
          <nav className="p-2 space-y-1 flex-1 overflow-y-auto">
            {!isCollapsed && (
              <div className="px-2 py-1 text-[9px] font-extrabold uppercase tracking-wider text-zinc-400">
                Menu Utama Studio
              </div>
            )}

            {NAV_ITEMS.map((item) => {
              const isAllowed = item.roleAllowed.includes(currentUser.role);
              if (!isAllowed) return null;
              const Icon = item.icon;
              const isActive = activeTab === item.id;

              return (
                <button
                  key={item.id}
                  onClick={() => handleTabChange(item.id as StudioTab)}
                  title={item.label}
                  className={`w-full text-left rounded-xl font-bold text-xs flex items-center transition group cursor-pointer ${
                    isCollapsed ? 'p-2.5 justify-center' : 'px-3 py-2 justify-between'
                  } ${
                    isActive
                      ? 'bg-red-50 text-red-600 border border-red-200/60'
                      : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100/80'
                  }`}
                >
                  <div className={`flex items-center ${isCollapsed ? 'justify-center' : 'gap-2.5'}`}>
                    <Icon
                      className={`w-4 h-4 shrink-0 transition-transform group-hover:scale-110 ${
                        isActive ? 'text-red-600' : 'text-zinc-400 group-hover:text-zinc-700'
                      }`}
                    />
                    {!isCollapsed && <span className="truncate">{item.label}</span>}
                  </div>
                  {!isCollapsed && isActive && <ChevronRight className="w-3.5 h-3.5 text-red-600 shrink-0" />}
                </button>
              );
            })}

          </nav>

          {/* Sidebar Footer: User Info & Actions */}
          <div className="p-2.5 border-t border-zinc-200 bg-zinc-50/50 space-y-2">
            {!isCollapsed ? (
              <>
                {/* Active User Card */}
                <div className="flex items-center justify-between bg-white p-2.5 rounded-xl border border-zinc-200 shadow-2xs">
                  <div className="flex items-center gap-2.5 min-w-0">
                    <div className="w-8 h-8 rounded-full bg-red-100 text-red-700 font-extrabold flex items-center justify-center text-xs shrink-0 border border-red-200 shadow-2xs">
                      {currentUser.name.charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                      <p className="text-xs font-bold text-zinc-900 truncate">{currentUser.name}</p>
                      <span className="text-[10px] font-bold capitalize text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-100 inline-block mt-0.5">
                        {currentUser.role === 'admin'
                          ? 'Admin Utama'
                          : currentUser.role === 'reviewer'
                          ? 'Reviuwer / Editor'
                          : 'Sahabat Penulis'}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Bottom Buttons */}
                <div className="flex items-center gap-1.5 pt-0.5">
                  <button
                    onClick={onBackToPortal}
                    className="flex-1 px-2.5 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold flex items-center justify-center gap-1 shadow-2xs transition cursor-pointer"
                    title="Kembali ke Portal Publik"
                  >
                    <ArrowLeft className="w-3.5 h-3.5" /> Portal Publik
                  </button>
                  <button
                    onClick={onLogout}
                    className="p-1.5 rounded-lg bg-white border border-zinc-200 hover:bg-red-50 text-zinc-500 hover:text-red-600 transition cursor-pointer shadow-2xs"
                    title="Keluar Studio"
                  >
                    <LogOut className="w-3.5 h-3.5" />
                  </button>
                </div>
              </>
            ) : (
              /* Collapsed Footer (Icon Only) */
              <div className="flex flex-col items-center gap-2 py-1">
                <div
                  className="w-8 h-8 rounded-full bg-red-100 text-red-700 font-extrabold flex items-center justify-center text-xs border border-red-200 cursor-pointer"
                  title={`${currentUser.name} (${currentUser.role})`}
                >
                  {currentUser.name.charAt(0).toUpperCase()}
                </div>
                <button
                  onClick={onBackToPortal}
                  className="p-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white transition cursor-pointer"
                  title="Kembali ke Portal Publik"
                >
                  <ArrowLeft className="w-4 h-4" />
                </button>
                <button
                  onClick={onLogout}
                  className="p-2 rounded-lg bg-white border border-zinc-200 hover:bg-red-50 text-zinc-500 hover:text-red-600 transition cursor-pointer"
                  title="Keluar Studio"
                >
                  <LogOut className="w-4 h-4" />
                </button>
              </div>
            )}
          </div>
        </div>
      </aside>

      {/* Overlay for mobile sidebar */}
      {mobileSidebarOpen && (
        <div
          onClick={() => setMobileSidebarOpen(false)}
          className="fixed inset-0 bg-black/40 backdrop-blur-xs z-40 md:hidden"
        />
      )}

      {/* ============================================================== */}
      {/* 2. MAIN CONTENT AREA (DYNAMIC LEFT PADDING) */}
      {/* ============================================================== */}
      <div
        className={`flex-1 min-w-0 flex flex-col min-h-screen transition-all duration-300 ${
          isCollapsed ? 'md:pl-16' : 'md:pl-52'
        }`}
      >
        {/* Main Content Body (Truly Single Header Bar inside view) */}
        <main className="flex-1 min-w-0 px-4 sm:px-6 pb-6 pt-0 w-full overflow-x-hidden">
          {loading ? (
            <div className="py-24 text-center text-zinc-400 space-y-3 bg-white rounded-2xl border border-zinc-200 shadow-2xs">
              <Sparkles className="w-8 h-8 text-red-600 mx-auto animate-spin" />
              <p className="text-sm font-bold text-zinc-700">Memuat Data Studio Redaksi...</p>
            </div>
          ) : (
            <>
              {(activeTab === 'artikel' || activeTab === 'buat-artikel') && (
                <ArticlesManager
                  key={activeTab}
                  articles={articles}
                  currentUser={currentUser}
                  galleryItems={galleryItems}
                  initialCreateMode={activeTab === 'buat-artikel'}
                  onNavigateToCreate={() => handleTabChange('buat-artikel')}
                  onSaveArticle={async (data) => {
                    await handleSaveArticle(data);
                    if (activeTab === 'buat-artikel') {
                      handleTabChange('artikel');
                    }
                  }}
                  onDeleteArticle={handleDeleteArticle}
                  onStatusChange={handleStatusChange}
                  onToggleSidebar={() => setIsCollapsed(!isCollapsed)}
                  isCollapsed={isCollapsed}
                  onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                  onBackToPortal={onBackToPortal}
                />
              )}

              {activeTab === 'galeri' && (
                <GalleryManager
                  items={galleryItems}
                  currentUser={currentUser}
                  onUploadImage={handleUploadImage}
                  onDeleteItem={handleDeleteGalleryItem}
                  onToggleSidebar={() => setIsCollapsed(!isCollapsed)}
                  isCollapsed={isCollapsed}
                  onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                  onBackToPortal={onBackToPortal}
                />
              )}

              {activeTab === 'video' && (
                <VideosManager
                  items={videoItems}
                  currentUser={currentUser}
                  onAddVideo={handleAddVideo}
                  onUpdateVideo={handleUpdateVideo}
                  onDeleteVideo={handleDeleteVideo}
                  onToggleSidebar={() => setIsCollapsed(!isCollapsed)}
                  isCollapsed={isCollapsed}
                  onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                  onBackToPortal={onBackToPortal}
                />
              )}

              {activeTab === 'pengguna' && currentUser.role === 'admin' && (
                <UsersManager
                  users={users}
                  currentUser={currentUser}
                  onAddUser={handleAddUser}
                  onUpdateUser={handleUpdateUser}
                  onDeleteUser={handleDeleteUser}
                  onToggleSidebar={() => setIsCollapsed(!isCollapsed)}
                  isCollapsed={isCollapsed}
                  onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                  onBackToPortal={onBackToPortal}
                />
              )}

              {activeTab === 'pengaturan' && currentUser.role === 'admin' && (
                <SettingsManager
                  currentUser={currentUser}
                  onBackup={handleBackup}
                  onRestore={handleRestore}
                  onToggleSidebar={() => setIsCollapsed(!isCollapsed)}
                  isCollapsed={isCollapsed}
                  onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                  onBackToPortal={onBackToPortal}
                />
              )}
            </>
          )}
        </main>
      </div>
    </div>
  );
};

