import React, { useState, useRef } from 'react';
import {
  Sliders,
  Download,
  Upload,
  Database,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  HardDrive,
  Menu,
  ExternalLink,
  PanelLeftClose,
  PanelLeftOpen
} from 'lucide-react';
import { User } from '../../types/studio';

interface SettingsManagerProps {
  currentUser: User;
  onBackup: () => void;
  onRestore: (jsonData: any, overwrite: boolean) => Promise<void>;
  onToggleSidebar?: () => void;
  isCollapsed?: boolean;
  onOpenMobileSidebar?: () => void;
  onBackToPortal?: () => void;
}

export const SettingsManager: React.FC<SettingsManagerProps> = ({
  currentUser,
  onBackup,
  onRestore,
  onToggleSidebar,
  isCollapsed,
  onOpenMobileSidebar,
  onBackToPortal,
}) => {
  const [restoring, setRestoring] = useState(false);
  const [restoreMessage, setRestoreMessage] = useState<string | null>(null);
  const [overwrite, setOverwrite] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setRestoring(true);
    setRestoreMessage('Membaca file cadangan backup...');

    try {
      const text = await file.text();
      const jsonData = JSON.parse(text);

      if (!jsonData.data || !jsonData.data.articles) {
        throw new Error('Format file cadangan tidak sesuai.');
      }

      await onRestore(jsonData.data, overwrite);
      setRestoreMessage('✓ Restore Data Berhasil! Seluruh data artikel, pengguna, galeri, dan video telah diperbarui.');
    } catch (err: any) {
      setRestoreMessage('⚠️ Gagal memproses restore: ' + err.message);
    } finally {
      setRestoring(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  return (
    <div className="space-y-4">
      {/* 1 TRULY UNIFIED SINGLE-LINE STICKY TOP HEADER BAR */}
      <div className="sticky top-0 z-30 -mt-4 sm:-mt-6 -mx-4 sm:-mx-6 mb-6 h-[57px] bg-white px-4 sm:px-6 border-b border-zinc-200 shadow-2xs flex items-center justify-between gap-3">
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
              <Sliders className="w-5 h-5 text-red-600" />
            </button>
          )}

          <h2 className="text-sm sm:text-base font-black text-zinc-900 tracking-tight whitespace-nowrap">
            Pengaturan Studio
          </h2>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* BACKUP SECTION */}
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-2xs space-y-4 flex flex-col justify-between">
          <div className="space-y-3">
            <div className="w-10 h-10 rounded-xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center">
              <Download className="w-5 h-5" />
            </div>
            <h3 className="text-base font-bold text-zinc-900">1. Cadangkan Data (Backup JSON)</h3>
            <p className="text-xs text-zinc-500 leading-relaxed">
              Unduh seluruh file basis data Studio (artikel, galeri foto, player video YouTube, dan data pengguna) ke dalam 1 file cadangan `.json` aman.
            </p>
          </div>

          <div className="pt-4 border-t border-zinc-100">
            <button
              onClick={onBackup}
              className="w-full py-2.5 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs shadow-2xs flex items-center justify-center gap-2 transition cursor-pointer"
            >
              <Download className="w-4 h-4" /> Unduh File Cadangan Backup JSON
            </button>
          </div>
        </div>

        {/* RESTORE SECTION */}
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-2xs space-y-4 flex flex-col justify-between">
          <div className="space-y-3">
            <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center">
              <Upload className="w-5 h-5" />
            </div>
            <h3 className="text-base font-bold text-zinc-900">2. Pulihkan Data (Restore JSON)</h3>
            <p className="text-xs text-zinc-500 leading-relaxed">
              Unggah file cadangan `.json` untuk memulihkan seluruh konten dan pengaturan Studio Redaksi ke kondisi semula.
            </p>

            <label className="flex items-center gap-2 text-xs font-semibold text-zinc-700 pt-2 cursor-pointer">
              <input
                type="checkbox"
                checked={overwrite}
                onChange={(e) => setOverwrite(e.target.checked)}
                className="rounded text-red-600 focus:ring-red-500"
              />
              <span>Timpa total data yang ada saat ini (Overwrite All)</span>
            </label>
          </div>

          <div className="pt-4 border-t border-zinc-100 space-y-2">
            <input
              type="file"
              ref={fileInputRef}
              accept=".json"
              onChange={handleFileSelect}
              className="hidden"
            />
            <button
              onClick={() => fileInputRef.current?.click()}
              disabled={restoring}
              className="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold text-xs shadow-2xs flex items-center justify-center gap-2 transition cursor-pointer"
            >
              <Upload className="w-4 h-4" /> Pilih File Cadangan Backup & Restore
            </button>
          </div>
        </div>
      </div>

      {restoreMessage && (
        <div className="p-4 rounded-2xl bg-white border border-zinc-200 text-xs font-bold text-zinc-800 shadow-2xs flex items-center gap-2">
          <Database className="w-4 h-4 text-red-600 shrink-0" />
          <span>{restoreMessage}</span>
        </div>
      )}
    </div>
  );
};
