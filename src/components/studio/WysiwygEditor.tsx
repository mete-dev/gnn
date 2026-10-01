import React, { useState, useRef, useEffect } from 'react';
import {
  Bold,
  Italic,
  Underline,
  Strikethrough,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  Quote,
  Link as LinkIcon,
  UploadCloud,
  FileImage,
  Check,
  FileText,
  Share2,
  Youtube,
  Instagram,
  Facebook,
  Video
} from 'lucide-react';
import { compressImageToUnder50KB } from '../../utils/imageCompressor';
import { GalleryItem } from '../../types/studio';

interface WysiwygEditorProps {
  content: string;
  onChange: (html: string) => void;
  galleryItems?: GalleryItem[];
  onSelectFromGallery?: (imageUrl: string) => void;
}

export const WysiwygEditor: React.FC<WysiwygEditorProps> = ({
  content,
  onChange,
  galleryItems = [],
}) => {
  const [compressing, setCompressing] = useState<boolean>(false);
  const [compressionMsg, setCompressionMsg] = useState<string | null>(null);
  const [showGalleryPicker, setShowGalleryPicker] = useState<boolean>(false);

  // Social Media Embed Modal State
  const [showSocialModal, setShowSocialModal] = useState<boolean>(false);
  const [socialPlatform, setSocialPlatform] = useState<'youtube' | 'instagram' | 'facebook'>('youtube');
  const [socialUrl, setSocialUrl] = useState<string>('');

  const editorRef = useRef<HTMLDivElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const savedSelectionRef = useRef<Range | null>(null);

  // Track the last HTML value we pushed INTO the editor from outside,
  // so we can distinguish "parent changed content" from "user typed".
  const lastExternalContent = useRef<string>('');

  // Sync content from parent → editor whenever the parent passes a NEW value
  // that didn't originate from the user's own typing in this editor.
  useEffect(() => {
    if (!editorRef.current) return;
    // If the new content is the same as what we last pushed externally, skip.
    if (content === lastExternalContent.current) return;
    // If the editor already shows exactly this content, skip (avoid cursor jump).
    if (editorRef.current.innerHTML === content) {
      lastExternalContent.current = content;
      return;
    }
    // Push the new content into the editor (e.g. when opening an existing article).
    editorRef.current.innerHTML = content;
    lastExternalContent.current = content;
  }, [content]);

  // Exec formatting command on contenteditable
  const format = (command: string, value: string | undefined = undefined) => {
    document.execCommand(command, false, value);
    if (editorRef.current) {
      onChange(editorRef.current.innerHTML);
    }
  };

  const insertHtmlAtCursor = (html: string) => {
    if (editorRef.current) {
      editorRef.current.focus();
      // Restore saved cursor position (lost when modal opened)
      if (savedSelectionRef.current) {
        const sel = window.getSelection();
        if (sel) {
          sel.removeAllRanges();
          sel.addRange(savedSelectionRef.current);
        }
        savedSelectionRef.current = null;
      }
      document.execCommand('insertHTML', false, html);
      const resultHtml = editorRef.current.innerHTML;
      lastExternalContent.current = resultHtml;
      onChange(resultHtml);
    }
  };

  const handleInput = () => {
    if (editorRef.current) {
      const html = editorRef.current.innerHTML;
      lastExternalContent.current = html; // mark as user-generated so useEffect skips it
      onChange(html);
    }
  };

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setCompressing(true);
    setCompressionMsg('Mengompresi gambar...');

    try {
      const res = await compressImageToUnder50KB(file, 50);
      setCompressionMsg(`✓ Foto berhasil disisipkan: ${res.sizeKb}KB`);
      format('insertImage', res.dataUrl);
      setTimeout(() => setCompressionMsg(null), 4000);
    } catch (err: any) {
      setCompressionMsg('⚠️ Gagal menyisipkan gambar: ' + err.message);
    } finally {
      setCompressing(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const insertLink = () => {
    const url = prompt('Masukkan URL tautan:');
    if (url) {
      format('createLink', url);
    }
  };

  const insertGalleryImage = (url: string) => {
    format('insertImage', url);
    setShowGalleryPicker(false);
  };

  const handleInsertSocialEmbed = () => {
    if (!socialUrl.trim()) {
      alert('Masukkan URL postingan media sosial!');
      return;
    }

    const trimmed = socialUrl.trim();
    let embedHtml = '';

    if (socialPlatform === 'youtube') {
      let videoId = '';
      const match = trimmed.match(/(?:v=|\/embed\/|\/watch\?v=|&v=|\/v\/|youtu\.be\/|\/shorts\/)([^#&?]*)/);
      if (match && match[1]) {
        videoId = match[1];
      }
      if (!videoId) {
        alert('URL YouTube tidak valid! Gunakan format contoh: https://www.youtube.com/watch?v=VIDEO_ID');
        return;
      }
      embedHtml = `
        <div class="embed-social embed-youtube my-5 relative rounded-2xl overflow-hidden shadow-md bg-black border border-zinc-200" contenteditable="false">
          <div class="aspect-video w-full">
            <iframe src="https://www.youtube-nocookie.com/embed/${videoId}" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-full h-full rounded-2xl"></iframe>
          </div>
        </div>
        <p><br></p>
      `;
    } else if (socialPlatform === 'instagram') {
      let postId = '';
      const match = trimmed.match(/(?:p|reel|tv)\/([^/?#]+)/);
      if (match && match[1]) {
        postId = match[1];
      }
      const embedSrc = postId ? `https://www.instagram.com/p/${postId}/embed` : trimmed;
      embedHtml = `
        <div class="embed-social embed-instagram my-5 max-w-md mx-auto rounded-2xl overflow-hidden shadow-md border border-zinc-200 bg-white p-2" contenteditable="false">
          <iframe src="${embedSrc}" width="100%" height="480" frameborder="0" scrolling="no" allowtransparency="true" class="w-full h-[480px] rounded-xl"></iframe>
        </div>
        <p><br></p>
      `;
    } else if (socialPlatform === 'facebook') {
      const encodedUrl = encodeURIComponent(trimmed);
      embedHtml = `
        <div class="embed-social embed-facebook my-5 max-w-lg mx-auto rounded-2xl overflow-hidden shadow-md border border-zinc-200 bg-white p-2" contenteditable="false">
          <iframe src="https://www.facebook.com/plugins/post.php?href=${encodedUrl}&show_text=true&width=500" width="100%" height="420" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" class="w-full h-[420px] rounded-xl"></iframe>
        </div>
        <p><br></p>
      `;
    }

    insertHtmlAtCursor(embedHtml);
    setSocialUrl('');
    setShowSocialModal(false);
  };

  return (
    <div className="border border-zinc-300 rounded-2xl bg-zinc-100/70 overflow-hidden shadow-xs">
      {/* Editor Header Toolbar (Floating Ribbon style like Microsoft Word) */}
      <div className="p-2.5 bg-white border-b border-zinc-200 sticky top-0 z-20 flex flex-wrap items-center justify-between gap-2 shadow-2xs">
        <div className="flex flex-wrap items-center gap-1">
          {/* Text Styling */}
          <button
            type="button"
            onClick={() => format('bold')}
            title="Tebal (Ctrl+B)"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <Bold className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => format('italic')}
            title="Miring (Ctrl+I)"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <Italic className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => format('underline')}
            title="Garis Bawah"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <Underline className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => format('strikeThrough')}
            title="Coret"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <Strikethrough className="w-4 h-4" />
          </button>

          <span className="w-px h-5 bg-zinc-200 mx-1" />

          {/* Headings */}
          <button
            type="button"
            onClick={() => format('formatBlock', '<h2>')}
            title="Judul Paragraf (H2)"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 font-bold transition flex items-center gap-1 text-xs cursor-pointer"
          >
            <Heading2 className="w-4 h-4 text-red-600" /> H2
          </button>
          <button
            type="button"
            onClick={() => format('formatBlock', '<h3>')}
            title="Sub Judul (H3)"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 font-bold transition flex items-center gap-1 text-xs cursor-pointer"
          >
            <Heading3 className="w-4 h-4 text-red-600" /> H3
          </button>

          <span className="w-px h-5 bg-zinc-200 mx-1" />

          {/* Lists & Blockquote */}
          <button
            type="button"
            onClick={() => format('insertUnorderedList')}
            title="Daftar Bulatan"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <List className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => format('insertOrderedList')}
            title="Daftar Angka"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <ListOrdered className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => format('formatBlock', '<blockquote>')}
            title="Kutipan (Blockquote)"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <Quote className="w-4 h-4" />
          </button>

          <span className="w-px h-5 bg-zinc-200 mx-1" />

          {/* Link */}
          <button
            type="button"
            onClick={insertLink}
            title="Sisipkan Tautan"
            className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-700 hover:text-zinc-900 transition cursor-pointer"
          >
            <LinkIcon className="w-4 h-4" />
          </button>

          {/* Media Actions */}
          <input
            type="file"
            ref={fileInputRef}
            onChange={handleImageUpload}
            accept="image/*"
            className="hidden"
          />
          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            disabled={compressing}
            title="Sisipkan Foto dari Perangkat"
            className="px-2.5 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
          >
            <UploadCloud className="w-3.5 h-3.5 text-red-400" />
            {compressing ? 'Mengompres...' : 'Sisipkan Foto'}
          </button>

          {galleryItems.length > 0 && (
            <button
              type="button"
              onClick={() => setShowGalleryPicker(true)}
              title="Sisipkan Foto dari Galeri Redaksi"
              className="px-2.5 py-1.5 rounded-xl bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs flex items-center gap-1.5 transition border border-red-200 cursor-pointer"
            >
              <FileImage className="w-3.5 h-3.5" />
              Pilih dari Galeri
            </button>
          )}

          <span className="w-px h-5 bg-zinc-200 mx-1" />

          {/* Tombol Sisipkan Media Sosial (YouTube, Instagram, Facebook) */}
          <button
            type="button"
            onClick={() => {
              // Simpan posisi kursor sebelum modal mengalihkan fokus
              const sel = window.getSelection();
              if (sel && sel.rangeCount > 0) {
                savedSelectionRef.current = sel.getRangeAt(0).cloneRange();
              }
              setShowSocialModal(true);
            }}
            title="Sisipkan Postingan Media Sosial (YouTube, Instagram, Facebook)"
            className="px-2.5 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs flex items-center gap-1.5 transition border border-blue-200 cursor-pointer"
          >
            <Video className="w-3.5 h-3.5 text-blue-600" />
            Sisipkan Media Sosial
          </button>
        </div>

        <div className="flex items-center gap-1 text-xs font-bold text-zinc-400 px-2 py-1">
          <FileText className="w-3.5 h-3.5 text-red-600" /> Lembar Kerja Dokumen
        </div>
      </div>

      {/* Compression notification banner */}
      {compressionMsg && (
        <div className="bg-emerald-50 border-b border-emerald-200 px-4 py-2 text-xs text-emerald-800 flex items-center gap-2">
          <Check className="w-4 h-4 text-emerald-600 shrink-0" />
          <span>{compressionMsg}</span>
        </div>
      )}

      {/* Microsoft Word Style Single Canvas Workspace Area */}
      <div className="p-4 sm:p-8 overflow-x-auto min-h-[520px] flex justify-center">
        {/* Paper Canvas Sheet */}
        <div className="w-full max-w-4xl bg-white rounded-xl border border-zinc-300 shadow-md min-h-[460px] p-6 sm:p-10 transition">
          <div
            ref={editorRef}
            contentEditable
            dir="ltr"
            onInput={handleInput}
            className="outline-none text-zinc-900 min-h-[400px] text-base leading-relaxed max-w-none focus:outline-none prose prose-zinc prose-img:rounded-xl prose-img:shadow-sm"
          />
        </div>
      </div>

      {/* Gallery Modal Picker */}
      {showGalleryPicker && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-zinc-200 rounded-2xl max-w-2xl w-full p-6 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b border-zinc-200 pb-3">
              <h3 className="text-base font-extrabold text-zinc-900 flex items-center gap-2">
                <FileImage className="w-5 h-5 text-red-600" /> Sisipkan Foto dari Galeri Redaksi
              </h3>
              <button
                type="button"
                onClick={() => setShowGalleryPicker(false)}
                className="text-zinc-400 hover:text-zinc-800 text-lg font-bold"
              >
                ✕
              </button>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[350px] overflow-y-auto p-1">
              {galleryItems.map((item) => (
                <div
                  key={item.id}
                  onClick={() => insertGalleryImage(item.imageUrl)}
                  className="group cursor-pointer rounded-xl border border-zinc-200 overflow-hidden hover:border-red-600 transition relative bg-zinc-50 shadow-2xs"
                >
                  <img
                    src={item.imageUrl}
                    alt={item.title}
                    className="w-full h-28 object-cover group-hover:scale-105 transition"
                  />
                  <div className="p-2 text-xs">
                    <p className="font-bold text-zinc-900 truncate">{item.title}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Social Media Embed Modal */}
      {showSocialModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-zinc-200 rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl">
            <div className="flex items-center justify-between border-b border-zinc-200 pb-3">
              <div className="flex items-center gap-2">
                <Video className="w-5 h-5 text-blue-600" />
                <h3 className="text-base font-extrabold text-zinc-900">Sisipkan Media Sosial</h3>
              </div>
              <button
                type="button"
                onClick={() => setShowSocialModal(false)}
                className="text-zinc-400 hover:text-zinc-800 text-lg font-bold"
              >
                ✕
              </button>
            </div>

            {/* Platform Tabs */}
            <div className="grid grid-cols-3 gap-2 bg-zinc-100 p-1 rounded-xl">
              <button
                type="button"
                onClick={() => setSocialPlatform('youtube')}
                className={`py-2 px-3 rounded-lg text-xs font-extrabold flex items-center justify-center gap-1.5 transition cursor-pointer ${
                  socialPlatform === 'youtube'
                    ? 'bg-red-600 text-white shadow-xs'
                    : 'text-zinc-600 hover:text-zinc-900'
                }`}
              >
                <Youtube className="w-4 h-4" /> YouTube
              </button>
              <button
                type="button"
                onClick={() => setSocialPlatform('instagram')}
                className={`py-2 px-3 rounded-lg text-xs font-extrabold flex items-center justify-center gap-1.5 transition cursor-pointer ${
                  socialPlatform === 'instagram'
                    ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow-xs'
                    : 'text-zinc-600 hover:text-zinc-900'
                }`}
              >
                <Instagram className="w-4 h-4" /> Instagram
              </button>
              <button
                type="button"
                onClick={() => setSocialPlatform('facebook')}
                className={`py-2 px-3 rounded-lg text-xs font-extrabold flex items-center justify-center gap-1.5 transition cursor-pointer ${
                  socialPlatform === 'facebook'
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'text-zinc-600 hover:text-zinc-900'
                }`}
              >
                <Facebook className="w-4 h-4" /> Facebook
              </button>
            </div>

            {/* Input URL Form */}
            <div className="space-y-2">
              <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700 block">
                URL Tautan {socialPlatform === 'youtube' ? 'YouTube' : socialPlatform === 'instagram' ? 'Instagram' : 'Facebook'} *
              </label>
              <input
                type="url"
                required
                placeholder={
                  socialPlatform === 'youtube'
                    ? 'Contoh: https://www.youtube.com/watch?v=...'
                    : socialPlatform === 'instagram'
                    ? 'Contoh: https://www.instagram.com/p/... atau https://www.instagram.com/reel/...'
                    : 'Contoh: https://www.facebook.com/permalink.php?story_fbid=...'
                }
                value={socialUrl}
                onChange={(e) => setSocialUrl(e.target.value)}
                className="w-full bg-zinc-50 border border-zinc-300 rounded-xl px-4 py-2.5 text-xs text-zinc-900 font-medium focus:border-blue-600 focus:bg-white focus:outline-none transition shadow-2xs"
              />
              <p className="text-[11px] text-zinc-400 font-medium">
                * Tautan embed akan otomatis diformat dan disisipkan langsung ke dalam lembar kerja artikel.
              </p>
            </div>

            {/* Modal Actions */}
            <div className="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
              <button
                type="button"
                onClick={() => setShowSocialModal(false)}
                className="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 bg-zinc-100 hover:bg-zinc-200 transition cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleInsertSocialEmbed}
                className="px-5 py-2 rounded-xl text-xs font-extrabold text-white bg-blue-600 hover:bg-blue-700 shadow-md transition flex items-center gap-1.5 cursor-pointer"
              >
                <Video className="w-4 h-4" /> Sisipkan ke Dokumen
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
