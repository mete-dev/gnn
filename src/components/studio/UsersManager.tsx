import React, { useState } from 'react';
import {
  Users,
  UserPlus,
  Shield,
  Edit,
  Trash2,
  CheckCircle,
  XCircle,
  Search,
  UserCheck,
  Menu,
  ExternalLink,
  Settings,
  KeyRound,
  Eye,
  EyeOff,
  Check,
  X,
  ArrowLeft,
  ChevronRight,
  ChevronLeft
} from 'lucide-react';
import { Role, User } from '../../types/studio';

interface UsersManagerProps {
  users: User[];
  currentUser: User;
  onAddUser: (userData: Partial<User>) => Promise<void>;
  onUpdateUser: (id: string, userData: Partial<User>) => Promise<void>;
  onDeleteUser: (id: string) => Promise<void>;
  onToggleSidebar?: () => void;
  isCollapsed?: boolean;
  onOpenMobileSidebar?: () => void;
  onBackToPortal?: () => void;
}

export const UsersManager: React.FC<UsersManagerProps> = ({
  users,
  currentUser,
  onAddUser,
  onUpdateUser,
  onDeleteUser,
  onToggleSidebar,
  isCollapsed,
  onOpenMobileSidebar,
  onBackToPortal,
}) => {
  const [search, setSearch] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);

  // Form states for Add User
  const [addName, setAddName] = useState('');
  const [addEmail, setAddEmail] = useState('');
  const [addPassword, setAddPassword] = useState('');
  const [showAddPassword, setShowAddPassword] = useState(false);
  const [addRole, setAddRole] = useState<Role>('sahabat');
  const [addUsername, setAddUsername] = useState('');
  const [addStatus, setAddStatus] = useState<'active' | 'inactive'>('active');

  // Form states for Edit User
  const [editName, setEditName] = useState('');
  const [editEmail, setEditEmail] = useState('');
  const [editPassword, setEditPassword] = useState('');
  const [showEditPassword, setShowEditPassword] = useState(false);
  const [editRole, setEditRole] = useState<Role>('sahabat');
  const [editUsername, setEditUsername] = useState('');
  const [editStatus, setEditStatus] = useState<'active' | 'inactive'>('active');
  const [saving, setSaving] = useState(false);

  // Quick view password in table
  const [revealedPasswords, setRevealedPasswords] = useState<{ [id: string]: boolean }>({});

  const toggleRevealPassword = (id: string) => {
    setRevealedPasswords((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  const handleOpenEdit = (user: User) => {
    setEditingUser(user);
    setEditName(user.name || '');
    setEditEmail(user.email || '');
    setEditPassword('');
    setShowEditPassword(false);
    setEditRole(user.role || 'sahabat');
    setEditUsername(user.username || '');
    setEditStatus(user.status || 'active');
  };

  const handleAddSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!addName || !addEmail) {
      alert('Nama dan Email wajib diisi!');
      return;
    }

    if (!addPassword) {
      alert('Password wajib diisi!');
      return;
    }

    setSaving(true);
    try {
      await onAddUser({
        name: addName,
        email: addEmail,
        password: addPassword,
        role: addRole,
        username: addUsername || addEmail.split('@')[0],
        status: addStatus,
      });
      setAddName('');
      setAddEmail('');
      setAddPassword('');
      setShowAddPassword(false);
      setAddRole('sahabat');
      setAddUsername('');
      setAddStatus('active');
      setShowAddModal(false);
    } catch (err: any) {
      alert('Gagal menambah pengguna: ' + err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleEditSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingUser) return;
    if (!editName || !editEmail) {
      alert('Nama dan Email wajib diisi!');
      return;
    }

    setSaving(true);
    try {
      const updateData: Partial<User> = {
        name: editName,
        email: editEmail,
        role: editRole,
        username: editUsername || editEmail.split('@')[0],
        status: editStatus,
      };

      if (editPassword && editPassword.trim() !== '') {
        updateData.password = editPassword.trim();
      }

      await onUpdateUser(editingUser.id, updateData);
      setEditingUser(null);
    } catch (err: any) {
      alert('Gagal memperbarui pengguna: ' + err.message);
    } finally {
      setSaving(false);
    }
  };

  const filteredUsers = users.filter(
    (u) =>
      u.name.toLowerCase().includes(search.toLowerCase()) ||
      u.email.toLowerCase().includes(search.toLowerCase()) ||
      u.role.toLowerCase().includes(search.toLowerCase()) ||
      (u.username && u.username.toLowerCase().includes(search.toLowerCase()))
  );

  const getRoleBadge = (userRole: Role) => {
    switch (userRole) {
      case 'admin':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
            Admin Utama
          </span>
        );
      case 'reviewer':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
            Reviuwer
          </span>
        );
      case 'sahabat':
      default:
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
            Sahabat Penulis
          </span>
        );
    }
  };

  if (showAddModal) {
    return (
      <div className="space-y-6">
        {/* Sticky Header Bar for Dedicated Add Page */}
        <div className="sticky top-0 z-30 -mx-4 sm:-mx-6 mb-6 h-[57px] bg-white px-4 sm:px-6 border-b border-zinc-200 shadow-2xs flex items-center justify-between gap-3 shrink-0">
          <div className="flex items-center gap-3">
            <button
              onClick={() => setShowAddModal(false)}
              className="p-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer flex items-center gap-1.5 text-xs font-bold"
              title="Kembali ke Daftar Pengguna"
            >
              <ArrowLeft className="w-4 h-4 text-red-600" />
              <span>Kembali Ke Daftar Pengguna</span>
            </button>
            <div className="h-4 w-px bg-zinc-300" />
            <h2 className="text-sm sm:text-base font-black text-zinc-900 tracking-tight flex items-center gap-2">
              <UserPlus className="w-5 h-5 text-red-600" /> Halaman Tambah Pengguna Baru
            </h2>
          </div>

          <button
            onClick={() => setShowAddModal(false)}
            className="px-3.5 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-bold text-xs transition cursor-pointer"
          >
            Tutup Halaman
          </button>
        </div>

        {/* Dedicated Page Card */}
        <div className="bg-white border border-zinc-200 rounded-3xl p-6 sm:p-8 shadow-xl max-w-4xl mx-auto space-y-6">
          <div className="border-b border-zinc-100 pb-4">
            <h3 className="text-lg font-black text-zinc-900">Formulir Pendaftaran Pengguna / Sahabat Penulis</h3>
            <p className="text-xs text-zinc-500">Lengkapi seluruh data berikut untuk menambahkan pengguna baru ke sistem redaksi GNN.</p>
          </div>

          <form onSubmit={handleAddSubmit} className="space-y-6">
            {/* 1. INFORMASI AKUN */}
            <div className="space-y-4">
              <h4 className="text-xs font-black uppercase text-red-600 tracking-wider border-b border-zinc-100 pb-1.5">
                1. Informasi Akun
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Nama Lengkap *</label>
                  <input
                    type="text"
                    required
                    placeholder="Masukkan nama lengkap"
                    value={addName}
                    onChange={(e) => setAddName(e.target.value)}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Username *</label>
                  <input
                    type="text"
                    required
                    placeholder="admin6908"
                    value={addUsername}
                    onChange={(e) => setAddUsername(e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''))}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Password *</label>
                  <div className="relative">
                    <input
                      type={showAddPassword ? 'text' : 'password'}
                      required
                      placeholder="••••••••"
                      value={addPassword}
                      onChange={(e) => setAddPassword(e.target.value)}
                      className="w-full bg-zinc-50 border border-zinc-200 rounded-xl pl-4 pr-10 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                    />
                    <button
                      type="button"
                      onClick={() => setShowAddPassword(!showAddPassword)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 transition"
                    >
                      {showAddPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Konfirmasi Password *</label>
                  <input
                    type="password"
                    required
                    placeholder="Ketik ulang password"
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                  />
                </div>
              </div>
            </div>

            {/* 2. DATA PRIBADI & KONTAK */}
            <div className="space-y-4">
              <h4 className="text-xs font-black uppercase text-red-600 tracking-wider border-b border-zinc-100 pb-1.5">
                2. Data Pribadi & Kontak
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Email *</label>
                  <input
                    type="email"
                    required
                    placeholder="nama@email.com"
                    value={addEmail}
                    onChange={(e) => setAddEmail(e.target.value)}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Nomor WhatsApp *</label>
                  <input
                    type="tel"
                    required
                    placeholder="0812xxxxxx"
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Jenis Kelamin *</label>
                  <select
                    defaultValue="Laki-laki"
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Tanggal Lahir *</label>
                  <input
                    type="date"
                    required
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                  />
                </div>
              </div>
            </div>

            {/* 3. DOMISILI (TEMPAT TINGGAL) */}
            <div className="space-y-4">
              <h4 className="text-xs font-black uppercase text-red-600 tracking-wider border-b border-zinc-100 pb-1.5">
                3. Domisili (Tempat Tinggal) & Hak Akses
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Negara *</label>
                  <select
                    defaultValue="Indonesia"
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-3 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="Indonesia">Indonesia</option>
                    <option value="Malaysia">Malaysia</option>
                    <option value="Singapura">Singapura</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Provinsi *</label>
                  <select
                    defaultValue=""
                    required
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-3 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="">Pilih Provinsi</option>
                    <option value="DKI Jakarta">DKI Jakarta</option>
                    <option value="Jawa Barat">Jawa Barat</option>
                    <option value="Jawa Tengah">Jawa Tengah</option>
                    <option value="DI Yogyakarta">DI Yogyakarta</option>
                    <option value="Jawa Timur">Jawa Timur</option>
                    <option value="Banten">Banten</option>
                    <option value="Bali">Bali</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Kabupaten/Kota *</label>
                  <select
                    defaultValue=""
                    required
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-3 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="">Pilih Kab/Kota</option>
                    <option value="Jakarta Pusat">Jakarta Pusat</option>
                    <option value="Jakarta Selatan">Jakarta Selatan</option>
                    <option value="Surabaya">Surabaya</option>
                    <option value="Bandung">Bandung</option>
                    <option value="Yogyakarta">Yogyakarta</option>
                  </select>
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-bold text-zinc-700">Detail Alamat Lengkap *</label>
                <textarea
                  rows={2}
                  required
                  placeholder="Nama Jalan, Gedung, RT/RW, Nomor Rumah"
                  className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Peran Hak Akses *</label>
                  <select
                    value={addRole}
                    onChange={(e) => setAddRole(e.target.value as Role)}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="sahabat">Sahabat (Akses Penulisan Karya Sendiri)</option>
                    <option value="reviewer">Reviuwer (Pengecekan & Publikasi Draft)</option>
                    <option value="admin">Admin (Akses Penuh Seluruh Sistem)</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Status Akun</label>
                  <select
                    value={addStatus}
                    onChange={(e) => setAddStatus(e.target.value as 'active' | 'inactive')}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                  </select>
                </div>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex justify-end gap-3 pt-4 border-t border-zinc-100">
              <button
                type="button"
                onClick={() => setShowAddModal(false)}
                className="px-5 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-zinc-100 hover:bg-zinc-200 transition cursor-pointer"
              >
                Batal
              </button>
              <button
                type="submit"
                disabled={saving}
                className="px-6 py-2.5 rounded-xl text-xs font-extrabold text-white bg-red-600 hover:bg-red-700 shadow-md transition cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
              >
                <UserCheck className="w-4 h-4" />
                <span>{saving ? 'Menyimpan Pengguna...' : 'Simpan Pengguna'}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    );
  }

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
              <Users className="w-5 h-5 text-red-600" />
            </button>
          )}

          <h2 className="text-sm sm:text-base font-black text-zinc-900 tracking-tight whitespace-nowrap">
            Pengelolaan Pengguna & Hak Akses
          </h2>
        </div>

        {/* Right Side: Search, Tambah Pengguna */}
        <div className="flex items-center gap-2 w-full md:w-auto justify-end">
          <div className="relative flex-1 md:w-56 min-w-[150px]">
            <Search className="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Cari pengguna..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-zinc-50 border border-zinc-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
            />
          </div>

          <button
            onClick={() => setShowAddModal(true)}
            className="px-3.5 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-2xs transition shrink-0 cursor-pointer whitespace-nowrap"
          >
            <UserPlus className="w-4 h-4" /> Tambah Pengguna
          </button>
        </div>
      </div>

      {/* Edit User Modal / Settings Modal */}
      {editingUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
          <div className="bg-white border border-zinc-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            {/* Modal Header */}
            <div className="p-5 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/50">
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-xl bg-red-50 text-red-600 border border-red-100">
                  <Settings className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-black text-zinc-900">Pengaturan / Edit Pengguna</h3>
                  <p className="text-[11px] text-zinc-500">Ubah data login, peran akses, dan status akun</p>
                </div>
              </div>
              <button
                onClick={() => setEditingUser(null)}
                className="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Modal Body / Form */}
            <form onSubmit={handleEditSubmit} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
              {/* Nama & Email */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Nama Lengkap *</label>
                  <input
                    type="text"
                    required
                    placeholder="Nama Lengkap"
                    value={editName}
                    onChange={(e) => setEditName(e.target.value)}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Alamat Email *</label>
                  <input
                    type="email"
                    required
                    placeholder="Alamat Email"
                    value={editEmail}
                    onChange={(e) => setEditEmail(e.target.value)}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
                  />
                </div>
              </div>

              {/* Username & URL Profil */}
              <div className="space-y-1">
                <label className="text-xs font-bold text-zinc-700">Username / Slug Penulis</label>
                <div className="flex items-center">
                  <span className="bg-zinc-100 border border-r-0 border-zinc-200 text-zinc-500 text-xs px-3 py-2 rounded-l-xl select-none font-mono">
                    /#/
                  </span>
                  <input
                    type="text"
                    placeholder="username"
                    value={editUsername}
                    onChange={(e) => setEditUsername(e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''))}
                    className="flex-1 bg-zinc-50 border border-zinc-200 rounded-r-xl px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-mono"
                  />
                </div>
                <p className="text-[10px] text-zinc-400">
                  Tautan profil artikel:{' '}
                  <span className="font-mono text-zinc-600">
                    http://localhost:3060/#/{editUsername || 'username'}
                  </span>
                </p>
              </div>

              {/* Password */}
              <div className="p-3.5 bg-amber-50/60 border border-amber-200/80 rounded-xl space-y-1.5">
                <label className="text-xs font-bold text-amber-900 flex items-center justify-between">
                  <span className="flex items-center gap-1.5">
                    <KeyRound className="w-3.5 h-3.5 text-amber-700" /> Password Login Studio
                  </span>
                  <span className="text-[10px] font-normal text-amber-700">(Kosongkan jika tidak ingin ganti)</span>
                </label>
                <div className="relative">
                  <input
                    type={showEditPassword ? 'text' : 'password'}
                    placeholder="Masukkan password baru..."
                    value={editPassword}
                    onChange={(e) => setEditPassword(e.target.value)}
                    className="w-full bg-white border border-amber-300 rounded-xl pl-3.5 pr-10 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 transition"
                  />
                  <button
                    type="button"
                    onClick={() => setShowEditPassword(!showEditPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 transition"
                  >
                    {showEditPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              </div>

              {/* Role & Status */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Peran / Hak Akses *</label>
                  <select
                    value={editRole}
                    onChange={(e) => setEditRole(e.target.value as Role)}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="sahabat">Sahabat Penulis (Akses Buat & Edit Karya Sendiri)</option>
                    <option value="reviewer">Reviuwer (Pengecekan, Sunting & Publish Draft)</option>
                    <option value="admin">Admin Utama (Akses Penuh Seluruh Sistem & Pengguna)</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-bold text-zinc-700">Status Akun *</label>
                  <select
                    value={editStatus}
                    onChange={(e) => setEditStatus(e.target.value as 'active' | 'inactive')}
                    className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-3.5 py-2 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition font-medium"
                  >
                    <option value="active">Aktif (Dapat Login & Akses Studio)</option>
                    <option value="inactive">Nonaktif (Akses Ditangguhkan)</option>
                  </select>
                </div>
              </div>

              {/* Modal Footer Actions */}
              <div className="flex items-center justify-end gap-2 pt-4 border-t border-zinc-100">
                <button
                  type="button"
                  onClick={() => setEditingUser(null)}
                  className="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 bg-zinc-100 hover:bg-zinc-200 transition cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="px-5 py-2 rounded-xl text-xs font-bold text-white bg-red-600 hover:bg-red-700 shadow-2xs transition cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
                >
                  <Check className="w-3.5 h-3.5" />
                  {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Users Table */}
      <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-2xs">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-zinc-50 text-zinc-500 font-bold border-b border-zinc-200 uppercase text-[10px]">
              <tr>
                <th className="p-4">Pengguna</th>
                <th className="p-4">Email</th>
                <th className="p-4">Password</th>
                <th className="p-4">Peran Akses</th>
                <th className="p-4">Status</th>
                <th className="p-4">Terdaftar</th>
                <th className="p-4 text-right">Aksi & Pengaturan</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100 text-zinc-800 font-medium">
              {filteredUsers.map((u) => (
                <tr key={u.id} className="hover:bg-zinc-50/80 transition">
                  <td className="p-4 font-bold text-zinc-900">
                    <div>
                      <p
                        className="font-bold text-zinc-900 hover:text-red-600 transition cursor-pointer"
                        onClick={() => handleOpenEdit(u)}
                      >
                        {u.name}
                      </p>
                      {u.username && (
                        <a
                          href={`#/${u.username}`}
                          target="_blank"
                          rel="noreferrer"
                          className="text-[10px] text-zinc-400 hover:text-red-600 font-mono flex items-center gap-1 transition"
                          title="Lihat Halaman Publik Penulis"
                        >
                          @{u.username} <ExternalLink className="w-2.5 h-2.5" />
                        </a>
                      )}
                    </div>
                  </td>
                  <td className="p-4 text-zinc-600">{u.email}</td>
                  <td className="p-4">
                    <div className="flex items-center gap-1.5 font-mono text-zinc-600">
                      <span>{revealedPasswords[u.id] ? u.password || '123456' : '••••••••'}</span>
                      <button
                        type="button"
                        onClick={() => toggleRevealPassword(u.id)}
                        className="p-1 rounded text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition"
                        title={revealedPasswords[u.id] ? 'Sembunyikan password' : 'Lihat password'}
                      >
                        {revealedPasswords[u.id] ? (
                          <EyeOff className="w-3.5 h-3.5" />
                        ) : (
                          <Eye className="w-3.5 h-3.5" />
                        )}
                      </button>
                    </div>
                  </td>
                  <td className="p-4">{getRoleBadge(u.role)}</td>
                  <td className="p-4">
                    {u.status === 'inactive' ? (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-500 border border-zinc-200">
                        Nonaktif
                      </span>
                    ) : (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Aktif
                      </span>
                    )}
                  </td>
                  <td className="p-4 text-zinc-400 text-[11px]">{u.createdAt || '2026-09-30'}</td>
                  <td className="p-4 text-right">
                    <div className="flex items-center justify-end gap-1.5">
                      <button
                        onClick={() => handleOpenEdit(u)}
                        className="px-2.5 py-1.5 rounded-lg bg-zinc-100 hover:bg-red-50 hover:text-red-600 text-zinc-700 text-xs font-bold flex items-center gap-1 transition cursor-pointer border border-zinc-200/60 shadow-2xs"
                        title="Pengaturan & Edit Data Pengguna"
                      >
                        <Edit className="w-3.5 h-3.5 text-red-600" />
                        <span>Settings</span>
                      </button>

                      <button
                        onClick={() => onDeleteUser(u.id)}
                        className="p-1.5 rounded-lg bg-zinc-100 hover:bg-red-100 text-zinc-500 hover:text-red-600 transition cursor-pointer border border-zinc-200/60 shadow-2xs"
                        title="Hapus Pengguna"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
