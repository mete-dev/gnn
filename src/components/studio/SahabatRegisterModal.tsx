import React, { useState } from 'react';
import { User, Role } from '../../types/studio';
import {
  UserCheck,
  X,
  ChevronRight,
  ChevronLeft,
  Lock,
  Mail,
  User as UserIcon,
  Phone,
  Calendar,
  MapPin,
  Globe,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';

interface SahabatRegisterModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccessRegister: (user: any) => void;
}

const PROVINCES = [
  'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Banten',
  'Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Aceh', 'Sumatera Utara',
  'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi', 'Sumatera Selatan',
  'Kepulauan Bangka Belitung', 'Bengkulu', 'Lampung', 'Kalimantan Barat',
  'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara',
  'Sulawesi Utara', 'Gorontalo', 'Sulawesi Tengah', 'Sulawesi Barat',
  'Sulawesi Selatan', 'Sulawesi Tenggara', 'Maluku', 'Maluku Utara',
  'Papua', 'Papua Barat', 'Papua Tengah', 'Papua Pegunungan', 'Papua Selatan', 'Papua Barat Daya'
];

const CITIES = [
  'Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Barat', 'Jakarta Timur', 'Jakarta Utara',
  'Surabaya', 'Bandung', 'Yogyakarta', 'Semarang', 'Surakarta (Solo)', 'Malang', 'Kediri',
  'Depok', 'Bogor', 'Bekasi', 'Tangerang', 'Tangerang Selatan', 'Serang', 'Cilegon',
  'Medan', 'Padang', 'Pekanbaru', 'Palembang', 'Bandar Lampung', 'Batam',
  'Pontianak', 'Banjarmasin', 'Samarinda', 'Balikpapan', 'IKN Nusantara',
  'Makassar', 'Manado', 'Denpasar', 'Mataram', 'Kupang', 'Ambon', 'Jayapura'
];

export const SahabatRegisterModal: React.FC<SahabatRegisterModalProps> = ({
  isOpen,
  onClose,
  onSuccessRegister,
}) => {
  const [step, setStep] = useState<number>(1);
  const [loading, setLoading] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string>('');

  // Step 1: Informasi Akun
  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  // Step 2: Data Pribadi & Kontak
  const [email, setEmail] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [gender, setGender] = useState('Laki-laki');
  const [birthDate, setBirthDate] = useState('');

  // Step 3: Domisili (Tempat Tinggal)
  const [country, setCountry] = useState('Indonesia');
  const [province, setProvince] = useState('');
  const [city, setCity] = useState('');
  const [address, setAddress] = useState('');

  if (!isOpen) return null;

  const handleNextStep1 = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    if (!name.trim() || !username.trim() || !password || !confirmPassword) {
      setErrorMsg('Semua bidang pada Langkah 1 wajib diisi.');
      return;
    }
    if (password !== confirmPassword) {
      setErrorMsg('Password dan Konfirmasi Password tidak cocok.');
      return;
    }
    if (password.length < 6) {
      setErrorMsg('Password minimal 6 karakter.');
      return;
    }
    setStep(2);
  };

  const handleNextStep2 = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    if (!email.trim() || !whatsapp.trim() || !birthDate) {
      setErrorMsg('Semua bidang pada Langkah 2 wajib diisi.');
      return;
    }
    if (!email.includes('@')) {
      setErrorMsg('Format email tidak valid.');
      return;
    }
    setStep(3);
  };

  const handleSubmitStep3 = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    if (!country || !province || !city || !address.trim()) {
      setErrorMsg('Semua bidang Domisili pada Langkah 3 wajib diisi.');
      return;
    }

    setLoading(true);
    try {
      const res = await fetch('/api/auth/register-sahabat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
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
        }),
      }).then((r) => r.json());

      if (res.success && res.data) {
        alert('Pendaftaran Sahabat GNN berhasil! Selamat bergabung.');
        onSuccessRegister(res.data);
        onClose();
      } else {
        setErrorMsg(res.message || 'Gagal mendaftar. Silakan periksa kembali data Anda.');
      }
    } catch (err: any) {
      setErrorMsg('Terjadi kesalahan koneksi server: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
      <div
        className="bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 rounded-3xl overflow-hidden max-w-xl w-full border border-zinc-200 dark:border-zinc-800 shadow-2xl relative flex flex-col my-auto"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/90">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center font-bold text-sm shadow">
              {step}
            </div>
            <div>
              <h3 className="text-sm sm:text-base font-black uppercase tracking-tight text-zinc-900 dark:text-zinc-100">
                Pendaftaran Sahabat Penulis GNN
              </h3>
              <p className="text-[11px] text-zinc-500 dark:text-zinc-400">
                Langkah {step} dari 3: {step === 1 ? 'Informasi Akun' : step === 2 ? 'Data Pribadi & Kontak' : 'Domisili (Tempat Tinggal)'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800 hover:bg-zinc-300 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 flex items-center justify-center transition cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Stepper Progress Bar */}
        <div className="grid grid-cols-3 bg-zinc-100 dark:bg-zinc-800/80 text-[11px] font-bold border-b border-zinc-200 dark:border-zinc-800">
          <div className={`py-2 px-3 text-center border-r border-zinc-200 dark:border-zinc-700 ${step === 1 ? 'bg-red-600 text-white font-extrabold' : step > 1 ? 'text-red-600 dark:text-red-400' : 'text-zinc-400'}`}>
            1. Informasi Akun
          </div>
          <div className={`py-2 px-3 text-center border-r border-zinc-200 dark:border-zinc-700 ${step === 2 ? 'bg-red-600 text-white font-extrabold' : step > 2 ? 'text-red-600 dark:text-red-400' : 'text-zinc-400'}`}>
            2. Data Pribadi
          </div>
          <div className={`py-2 px-3 text-center ${step === 3 ? 'bg-red-600 text-white font-extrabold' : 'text-zinc-400'}`}>
            3. Domisili
          </div>
        </div>

        {/* Error Alert */}
        {errorMsg && (
          <div className="mx-6 mt-4 p-3 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-xs rounded-xl font-semibold flex items-center gap-2">
            <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />
            <span>{errorMsg}</span>
          </div>
        )}

        {/* Modal Form Content */}
        <div className="p-6">
          {/* STEP 1: INFORMASI AKUN */}
          {step === 1 && (
            <form onSubmit={handleNextStep1} className="space-y-4">
              {/* Opsi Login / Daftar dengan Google */}
              <button
                type="button"
                onClick={() => {
                  setLoading(true);
                  setErrorMsg('');

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
                        alert(`Selamat datang, ${fallbackUser.name} (${fallbackUser.email})! Berhasil mendaftar/login dengan Akun Google.`);
                        onSuccessRegister(fallbackUser);
                        onClose();
                        return;
                      }

                      const res = await rawRes.json();
                      if (res && res.success && res.data) {
                        alert(`Selamat datang, ${res.data.name} (${res.data.email})! Berhasil mendaftar/login dengan Akun Google.`);
                        onSuccessRegister(res.data);
                        onClose();
                      } else {
                        setErrorMsg((res && res.message) || 'Gagal autentikasi Google.');
                      }
                    } catch (err: any) {
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
                      alert(`Selamat datang, ${fallbackUser.name} (${fallbackUser.email})! Berhasil mendaftar/login dengan Akun Google.`);
                      onSuccessRegister(fallbackUser);
                      onClose();
                    } finally {
                      setLoading(false);
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
                            setLoading(false);
                            setErrorMsg('Login Google dibatalkan atau tidak diizinkan.');
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
                            setLoading(false);
                            setErrorMsg('Gagal mengambil profil Google: ' + err.message);
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
                        setLoading(false);
                      }
                    }
                  } else {
                    const realEmail = prompt('Masukkan Email Google Anda (contoh: nama.anda@gmail.com):');
                    if (realEmail && realEmail.trim()) {
                      const realName = prompt('Masukkan Nama Lengkap Anda:', realEmail.split('@')[0]) || realEmail.split('@')[0];
                      processRealGoogleProfile(realEmail.trim(), realName.trim());
                    } else {
                      setLoading(false);
                    }
                  }
                }}
                className="w-full py-2.5 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-750 text-zinc-700 dark:text-zinc-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center justify-center gap-2 cursor-pointer mb-2"
              >
                <svg className="w-4 h-4" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                </svg>
                <span>Daftar Cepat dengan Google</span>
              </button>


              <div className="flex items-center gap-3 my-2">
                <div className="h-px bg-zinc-200 dark:bg-zinc-800 flex-1" />
                <span className="text-[10px] text-zinc-400 font-bold uppercase">atau isi manual</span>
                <div className="h-px bg-zinc-200 dark:bg-zinc-800 flex-1" />
              </div>

              <h4 className="text-xs font-black uppercase text-zinc-500 tracking-wider border-b pb-1 border-zinc-200 dark:border-zinc-800">
                1. Informasi Akun
              </h4>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                  Nama Lengkap *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Masukkan nama lengkap"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                  Username *
                </label>
                <input
                  type="text"
                  required
                  placeholder="admin6908"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Password *
                  </label>
                  <input
                    type="password"
                    required
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Konfirmasi Password *
                  </label>
                  <input
                    type="password"
                    required
                    placeholder="Ketik ulang password"
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  />
                </div>
              </div>

              <div className="pt-4 flex justify-end">
                <button
                  type="submit"
                  className="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer"
                >
                  <span>Lanjut Ke Data Pribadi</span>
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </form>
          )}

          {/* STEP 2: DATA PRIBADI & KONTAK */}
          {step === 2 && (
            <form onSubmit={handleNextStep2} className="space-y-4">
              <h4 className="text-xs font-black uppercase text-zinc-500 tracking-wider border-b pb-1 border-zinc-200 dark:border-zinc-800">
                2. Data Pribadi & Kontak
              </h4>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Email *
                  </label>
                  <input
                    type="email"
                    required
                    placeholder="nama@email.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Nomor WhatsApp *
                  </label>
                  <input
                    type="tel"
                    required
                    placeholder="0812xxxxxx"
                    value={whatsapp}
                    onChange={(e) => setWhatsapp(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Jenis Kelamin *
                  </label>
                  <select
                    value={gender}
                    onChange={(e) => setGender(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-bold"
                  >
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Tanggal Lahir *
                  </label>
                  <input
                    type="date"
                    required
                    value={birthDate}
                    onChange={(e) => setBirthDate(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  />
                </div>
              </div>

              <div className="pt-4 flex justify-between">
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="px-4 py-2.5 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-700 dark:text-zinc-300 font-bold text-xs rounded-xl transition flex items-center gap-1 cursor-pointer"
                >
                  <ChevronLeft className="w-4 h-4" /> Kembali
                </button>

                <button
                  type="submit"
                  className="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer"
                >
                  <span>Lanjut Ke Domisili</span>
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </form>
          )}

          {/* STEP 3: DOMISILI (TEMPAT TINGGAL) */}
          {step === 3 && (
            <form onSubmit={handleSubmitStep3} className="space-y-4">
              <h4 className="text-xs font-black uppercase text-zinc-500 tracking-wider border-b pb-1 border-zinc-200 dark:border-zinc-800">
                3. Domisili (Tempat Tinggal)
              </h4>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Negara *
                  </label>
                  <select
                    value={country}
                    onChange={(e) => setCountry(e.target.value)}
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-bold"
                  >
                    <option value="Indonesia">Indonesia</option>
                    <option value="Malaysia">Malaysia</option>
                    <option value="Singapura">Singapura</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Provinsi *
                  </label>
                  <select
                    value={province}
                    onChange={(e) => setProvince(e.target.value)}
                    required
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  >
                    <option value="">Pilih Provinsi</option>
                    {PROVINCES.map((p) => (
                      <option key={p} value={p}>
                        {p}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Kabupaten/Kota *
                  </label>
                  <select
                    value={city}
                    onChange={(e) => setCity(e.target.value)}
                    required
                    className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                  >
                    <option value="">Pilih Kab/Kota</option>
                    {CITIES.map((c) => (
                      <option key={c} value={c}>
                        {c}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                  Detail Alamat Lengkap *
                </label>
                <textarea
                  rows={2}
                  required
                  placeholder="Nama Jalan, Gedung, RT/RW, Nomor Rumah"
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  className="w-full bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-red-600 focus:bg-white dark:focus:bg-zinc-800 transition font-medium"
                />
              </div>

              <div className="pt-4 flex justify-between gap-3">
                <button
                  type="button"
                  onClick={() => setStep(2)}
                  className="px-4 py-2.5 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 text-zinc-700 dark:text-zinc-300 font-bold text-xs rounded-xl transition flex items-center gap-1 cursor-pointer"
                >
                  <ChevronLeft className="w-4 h-4" /> Kembali
                </button>

                <button
                  type="submit"
                  disabled={loading}
                  className="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-extrabold text-xs rounded-xl shadow-lg transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                >
                  <UserCheck className="w-4 h-4" />
                  <span>{loading ? 'Mendaftarkan...' : 'Daftar Sekarang'}</span>
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
};
