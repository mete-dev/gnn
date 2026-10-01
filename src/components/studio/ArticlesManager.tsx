import React, { useState } from 'react';
import {
  FileText,
  Plus,
  Edit,
  Trash2,
  CheckCircle2,
  Clock,
  Send,
  Eye,
  Sparkles,
  Filter,
  Search,
  Tag,
  AlertCircle,
  ChevronDown,
  PanelLeftClose,
  PanelLeftOpen,
  Menu,
  ExternalLink,
  Image as ImageIcon,
  Upload,
  Crop,
  UserCheck,
  ArrowLeft
} from 'lucide-react';
import { Article, ArticleStatus, GalleryItem, User } from '../../types/studio';
import { WysiwygEditor } from './WysiwygEditor';
import { compressImageToUnder50KB } from '../../utils/imageCompressor';
import { ImageCropperModal, AspectRatioType } from './ImageCropperModal';

interface ArticlesManagerProps {
  articles: Article[];
  currentUser: User;
  galleryItems: GalleryItem[];
  initialCreateMode?: boolean;
  onNavigateToCreate?: () => void;
  onSaveArticle: (articleData: Partial<Article>) => Promise<void>;
  onDeleteArticle: (id: string) => Promise<void>;
  onStatusChange: (id: string, newStatus: ArticleStatus) => Promise<void>;
  onToggleSidebar?: () => void;
  isCollapsed?: boolean;
  onOpenMobileSidebar?: () => void;
  onBackToPortal?: () => void;
}

const INDONESIAN_CITIES = [
  // 1. DKI JAKARTA, BANTEN, JAWA BARAT, JAWA TENGAH, DIY, JAWA TIMUR
  'Jakarta', 'Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Barat', 'Jakarta Timur', 'Jakarta Utara', 'Kab. Kepulauan Seribu',
  'Surabaya', 'Bandung', 'Yogyakarta', 'Semarang', 'Surakarta (Solo)', 'Malang', 'Kediri', 'Madiun', 'Magelang', 'Mojokerto',
  'Probolinggo', 'Salatiga', 'Tegal', 'Blitar', 'Pasuruan', 'Batu', 'Cirebon', 'Sukabumi', 'Tasikmalaya', 'Banjar', 'Cimahi',
  'Depok', 'Bogor', 'Bekasi', 'Tangerang', 'Tangerang Selatan', 'Serang', 'Cilegon',
  'Kab. Bandung', 'Kab. Bandung Barat', 'Kab. Bekasi', 'Kab. Bogor', 'Kab. Ciamis', 'Kab. Cianjur', 'Kab. Cirebon',
  'Kab. Garut', 'Kab. Indramayu', 'Kab. Karawang', 'Kab. Kuningan', 'Kab. Majalengka', 'Kab. Pangandaran', 'Kab. Purwakarta',
  'Kab. Subang', 'Kab. Sukabumi', 'Kab. Sumedang', 'Kab. Tasikmalaya', 'Kab. Banjarnegara', 'Kab. Banyumas', 'Kab. Batang',
  'Kab. Blora', 'Kab. Boyolali', 'Kab. Brebes', 'Kab. Cilacap', 'Kab. Demak', 'Kab. Grobogan', 'Kab. Jepara', 'Kab. Karanganyar',
  'Kab. Kebumen', 'Kab. Kendal', 'Kab. Klaten', 'Kab. Kudus', 'Kab. Magelang', 'Kab. Pati', 'Kab. Pekalongan', 'Kab. Pemalang',
  'Kab. Purbalingga', 'Kab. Purworejo', 'Kab. Rembang', 'Kab. Semarang', 'Kab. Sragen', 'Kab. Sukoharjo', 'Kab. Tegal',
  'Kab. Temanggung', 'Kab. Wonogiri', 'Kab. Wonosobo', 'Kab. Bangkalan', 'Kab. Banyuwangi', 'Kab. Blitar', 'Kab. Bojonegoro',
  'Kab. Bondowoso', 'Kab. Gresik', 'Kab. Jember', 'Kab. Jombang', 'Kab. Kediri', 'Kab. Lamongan', 'Kab. Lumajang', 'Kab. Madiun',
  'Kab. Magetan', 'Kab. Malang', 'Kab. Mojokerto', 'Kab. Nganjuk', 'Kab. Ngawi', 'Kab. Pacitan', 'Kab. Pamekasan', 'Kab. Pasuruan',
  'Kab. Ponorogo', 'Kab. Probolinggo', 'Kab. Sampang', 'Kab. Sidoarjo', 'Kab. Situbondo', 'Kab. Sumenep', 'Kab. Trenggalek',
  'Kab. Tuban', 'Kab. Tulungagung', 'Kab. Lebak', 'Kab. Pandeglang', 'Kab. Serang', 'Kab. Tangerang', 'Kab. Bantul',
  'Kab. Gunungkidul', 'Kab. Kulon Progo', 'Kab. Sleman',

  // 2. SUMATERA (Aceh, Sumut, Sumbar, Riau, Kepri, Jambi, Sumsel, Babel, Bengkulu, Lampung)
  'Banda Aceh', 'Sabang', 'Lhokseumawe', 'Langsa', 'Subulussalam', 'Medan', 'Pematangsiantar', 'Sibolga', 'Tanjungbalai',
  'Binjai', 'Tebing Tinggi', 'Padangsidimpuan', 'Gunungsitoli', 'Padang', 'Solok', 'Sawahlunto', 'Padang Panjang',
  'Bukittinggi', 'Payakumbuh', 'Pariaman', 'Pekanbaru', 'Dumai', 'Batam', 'Tanjungpinang', 'Jambi', 'Sungaipenuh',
  'Palembang', 'Prabumulih', 'Pagar Alam', 'Lubuklinggau', 'Pangkalpinang', 'Bengkulu', 'Bandar Lampung', 'Metro',
  'Kab. Aceh Barat', 'Kab. Aceh Barat Daya', 'Kab. Aceh Besar', 'Kab. Aceh Jaya', 'Kab. Aceh Selatan', 'Kab. Aceh Singkil',
  'Kab. Aceh Tamiang', 'Kab. Aceh Tengah', 'Kab. Aceh Tenggara', 'Kab. Aceh Timur', 'Kab. Aceh Utara', 'Kab. Bener Meriah',
  'Kab. Bireuen', 'Kab. Gayo Lues', 'Kab. Nagan Raya', 'Kab. Pidie', 'Kab. Pidie Jaya', 'Kab. Simeulue', 'Kab. Asahan',
  'Kab. Batu Bara', 'Kab. Dairi', 'Kab. Deli Serdang', 'Kab. Humbang Hasundutan', 'Kab. Karo', 'Kab. Labuhanbatu',
  'Kab. Labuhanbatu Selatan', 'Kab. Labuhanbatu Utara', 'Kab. Langkat', 'Kab. Mandailing Natal', 'Kab. Nias',
  'Kab. Nias Barat', 'Kab. Nias Selatan', 'Kab. Nias Utara', 'Kab. Padang Lawas', 'Kab. Padang Lawas Utara',
  'Kab. Pakpak Bharat', 'Kab. Samosir', 'Kab. Serdang Bedagai', 'Kab. Simalungun', 'Kab. Tapanuli Selatan',
  'Kab. Tapanuli Tengah', 'Kab. Tapanuli Utara', 'Kab. Toba', 'Kab. Agam', 'Kab. Dharmasraya', 'Kab. Kepulauan Mentawai',
  'Kab. Lima Puluh Kota', 'Kab. Padang Pariaman', 'Kab. Pasaman', 'Kab. Pasaman Barat', 'Kab. Pesisir Selatan',
  'Kab. Sijunjung', 'Kab. Solok', 'Kab. Solok Selatan', 'Kab. Tanah Datar', 'Kab. Bengkalis', 'Kab. Indragiri Hilir',
  'Kab. Indragiri Hulu', 'Kab. Kampar', 'Kab. Kepulauan Meranti', 'Kab. Kuantan Singingi', 'Kab. Pelalawan', 'Kab. Rokan Hilir',
  'Kab. Rokan Hulu', 'Kab. Siak', 'Kab. Bintan', 'Kab. Karimun', 'Kab. Kepulauan Anambas', 'Kab. Lingga', 'Kab. Natuna',
  'Kab. Batanghari', 'Kab. Bungo', 'Kab. Kerinci', 'Kab. Merangin', 'Kab. Muaro Jambi', 'Kab. Sarolangun', 'Kab. Tanjung Jabung Barat',
  'Kab. Tanjung Jabung Timur', 'Kab. Tebo', 'Kab. Banyuasin', 'Kab. Empat Lawang', 'Kab. Lahat', 'Kab. Muara Enim',
  'Kab. Musi Banyuasin', 'Kab. Musi Rawas', 'Kab. Musi Rawas Utara', 'Kab. Ogan Ilir', 'Kab. Ogan Komering Ilir',
  'Kab. Ogan Komering Ulu', 'Kab. Ogan Komering Ulu Selatan', 'Kab. Ogan Komering Ulu Timur', 'Kab. Penukal Abab Lematang Ilir',
  'Kab. Bangka', 'Kab. Bangka Barat', 'Kab. Bangka Selatan', 'Kab. Bangka Tengah', 'Kab. Belitung', 'Kab. Belitung Timur',
  'Kab. Bengkulu Selatan', 'Kab. Bengkulu Tengah', 'Kab. Bengkulu Utara', 'Kab. Kaur', 'Kab. Kepahiang', 'Kab. Lebong',
  'Kab. Mukomuko', 'Kab. Rejang Lebong', 'Kab. Seluma', 'Kab. Lampung Barat', 'Kab. Lampung Selatan', 'Kab. Lampung Tengah',
  'Kab. Lampung Timur', 'Kab. Lampung Utara', 'Kab. Mesuji', 'Kab. Pesawaran', 'Kab. Pesisir Barat', 'Kab. Pringsewu',
  'Kab. Tanggamus', 'Kab. Tulang Bawang', 'Kab. Tulang Bawang Barat', 'Kab. Way Kanan',

  // 3. KALIMANTAN (Kalbar, Kalteng, Kalsel, Kaltim, Kaltara)
  'Pontianak', 'Singkawang', 'Palangkaraya', 'Banjarmasin', 'Banjarbaru', 'Samarinda', 'Balikpapan', 'Bontang', 'Tarakan', 'Nusantara (IKN)',
  'Kab. Bengkayang', 'Kab. Kapuas Hulu', 'Kab. Kayong Utara', 'Kab. Ketapang', 'Kab. Kubu Raya', 'Kab. Landak', 'Kab. Melawi',
  'Kab. Mempawah', 'Kab. Sambas', 'Kab. Sanggau', 'Kab. Sekadau', 'Kab. Sintang', 'Kab. Barito Selatan', 'Kab. Barito Timur',
  'Kab. Barito Utara', 'Kab. Gunung Mas', 'Kab. Kapuas', 'Kab. Katingan', 'Kab. Kotawaringin Barat', 'Kab. Kotawaringin Timur',
  'Kab. Lamandau', 'Kab. Murung Raya', 'Kab. Pulang Pisau', 'Kab. Seruyan', 'Kab. Sukamara', 'Kab. Balangan', 'Kab. Banjar',
  'Kab. Barito Kuala', 'Kab. Hulu Sungai Selatan', 'Kab. Hulu Sungai Tengah', 'Kab. Hulu Sungai Utara', 'Kab. Kotabaru',
  'Kab. Tabalong', 'Kab. Tanah Bumbu', 'Kab. Tanah Laut', 'Kab. Tapin', 'Kab. Berau', 'Kab. Kutai Barat', 'Kab. Kutai Kartanegara',
  'Kab. Kutai Timur', 'Kab. Mahakam Ulu', 'Kab. Paser', 'Kab. Penajam Paser Utara', 'Kab. Bulungan', 'Kab. Malinau',
  'Kab. Nunukan', 'Kab. Tana Tidung',

  // 4. SULAWESI (Sulut, Gorontalo, Sulteng, Sulbar, Sulsel, Sultra)
  'Manado', 'Bitung', 'Tomohon', 'Kotamobagu', 'Gorontalo', 'Palu', 'Mamuju', 'Makassar', 'Parepare', 'Palopo', 'Kendari', 'Baubau',
  'Kab. Bolaang Mongondow', 'Kab. Bolaang Mongondow Selatan', 'Kab. Bolaang Mongondow Timur', 'Kab. Bolaang Mongondow Utara',
  'Kab. Kepulauan Sangihe', 'Kab. Kepulauan Siau Tagulandang Biaro', 'Kab. Kepulauan Talaud', 'Kab. Minahasa', 'Kab. Minahasa Selatan',
  'Kab. Minahasa Tenggara', 'Kab. Minahasa Utara', 'Kab. Boalemo', 'Kab. Bone Bolango', 'Kab. Gorontalo', 'Kab. Gorontalo Utara',
  'Kab. Pohuwato', 'Kab. Banggai', 'Kab. Banggai Kepulauan', 'Kab. Banggai Laut', 'Kab. Buol', 'Kab. Donggala', 'Kab. Morowali',
  'Kab. Morowali Utara', 'Kab. Parigi Moutong', 'Kab. Poso', 'Kab. Sigi', 'Kab. Tojo Una-Una', 'Kab. Tolitoli', 'Kab. Majene',
  'Kab. Mamasa', 'Kab. Mamuju', 'Kab. Mamuju Tengah', 'Kab. Pasangkayu', 'Kab. Polewali Mandar', 'Kab. Bantaeng', 'Kab. Barru',
  'Kab. Bone', 'Kab. Bulukumba', 'Kab. Enrekang', 'Kab. Gowa', 'Kab. Jeneponto', 'Kab. Kepulauan Selayar', 'Kab. Luwu',
  'Kab. Luwu Timur', 'Kab. Luwu Utara', 'Kab. Maros', 'Kab. Pangkajene dan Kepulauan', 'Kab. Pinrang', 'Kab. Sidenreng Rappang',
  'Kab. Sinjai', 'Kab. Soppeng', 'Kab. Takalar', 'Kab. Tana Toraja', 'Kab. Toraja Utara', 'Kab. Wajo', 'Kab. Bombana',
  'Kab. Buton', 'Kab. Buton Selatan', 'Kab. Buton Tengah', 'Kab. Buton Utara', 'Kab. Kolaka', 'Kab. Kolaka Timur',
  'Kab. Kolaka Utara', 'Kab. Konawe', 'Kab. Konawe Kepulauan', 'Kab. Konawe Selatan', 'Kab. Konawe Utara', 'Kab. Muna',
  'Kab. Muna Barat', 'Kab. Wakatobi',

  // 5. BALI, NUSA TENGGARA BARAT (NTB), NUSA TENGGARA TIMUR (NTT)
  'Denpasar', 'Mataram', 'Bima', 'Kupang',
  'Kab. Badung', 'Kab. Bangli', 'Kab. Buleleng', 'Kab. Gianyar', 'Kab. Jembrana', 'Kab. Karangasem', 'Kab. Klungkung', 'Kab. Tabanan',
  'Kab. Bima', 'Kab. Dompu', 'Kab. Lombok Barat', 'Kab. Lombok Tengah', 'Kab. Lombok Timur', 'Kab. Lombok Utara', 'Kab. Sumbawa',
  'Kab. Sumbawa Barat', 'Kab. Alor', 'Kab. Belu', 'Kab. Ende', 'Kab. Flores Timur', 'Kab. Kupang', 'Kab. Lembata', 'Kab. Malaka',
  'Kab. Manggarai', 'Kab. Manggarai Barat', 'Kab. Manggarai Timur', 'Kab. Nagekeo', 'Kab. Ngada', 'Kab. Rote Ndao', 'Kab. Sabu Raijua',
  'Kab. Sikka', 'Kab. Sumba Barat', 'Kab. Sumba Barat Daya', 'Kab. Sumba Tengah', 'Kab. Sumba Timur', 'Kab. Timor Tengah Selatan',
  'Kab. Timor Tengah Utara',

  // 6. MALUKU & PAPUA (Maluku, Malut, Papua, Papua Barat, PapBar Daya, PapTeng, PapSatan, PapPeg)
  'Ambon', 'Tual', 'Ternate', 'Tidore Kepulauan', 'Sofifi', 'Jayapura', 'Sorong', 'Manokwari', 'Merauke', 'Nabire', 'Wamena', 'Timika', 'Biak',
  'Kab. Buru', 'Kab. Buru Selatan', 'Kab. Kepulauan Aru', 'Kab. Kepulauan Tanimbar', 'Kab. Maluku Barat Daya', 'Kab. Maluku Tengah',
  'Kab. Maluku Tenggara', 'Kab. Seram Bagian Barat', 'Kab. Seram Bagian Timur', 'Kab. Halmahera Barat', 'Kab. Halmahera Selatan',
  'Kab. Halmahera Tengah', 'Kab. Halmahera Timur', 'Kab. Halmahera Utara', 'Kab. Kepulauan Sula', 'Kab. Pulau Taliabu', 'Kab. Pulau Morotai',
  'Kab. Biak Numfor', 'Kab. Jayapura', 'Kab. Keerom', 'Kab. Kepulauan Yapen', 'Kab. Mamberamo Raya', 'Kab. Sarmi', 'Kab. Supiori',
  'Kab. Waropen', 'Kab. Fakfak', 'Kab. Kaimana', 'Kab. Manokwari', 'Kab. Manokwari Selatan', 'Kab. Pegunungan Arfak', 'Kab. Teluk Bintuni',
  'Kab. Teluk Wondama', 'Kab. Maybrat', 'Kab. Raja Ampat', 'Kab. Sorong', 'Kab. Sorong Selatan', 'Kab. Tambrauw', 'Kab. Deiyai',
  'Kab. Dogiyai', 'Kab. Intan Jaya', 'Kab. Mimika', 'Kab. Nabire', 'Kab. Paniai', 'Kab. Puncak', 'Kab. Puncak Jaya', 'Kab. Asmat',
  'Kab. Boven Digoel', 'Kab. Mappi', 'Kab. Merauke', 'Kab. Jayawijaya', 'Kab. Lanny Jaya', 'Kab. Mamberamo Tengah', 'Kab. Nduga',
  'Kab. Pegunungan Bintang', 'Kab. Tolikara', 'Kab. Yahukimo', 'Kab. Yalimo'
];

const WORLD_CAPITALS = [
  'Tokyo', 'Washington D.C.', 'London', 'Beijing', 'Canberra', 'Paris', 'Berlin', 'Moscow', 'Riyadh',
  'Abu Dhabi', 'Kuala Lumpur', 'Singapore', 'Bangkok', 'Manila', 'Hanoi', 'Seoul', 'New Delhi', 'Rome',
  'Ottawa', 'Brasilia', 'Cairo', 'Ankara', 'Amsterdam', 'Madrid', 'Wellington', 'Tehran', 'Islamabad',
  'Doha', 'Muscat', 'Dublin', 'Brussels', 'Stockholm', 'Oslo', 'Copenhagen', 'Helsinki', 'Vienna',
  'Bern', 'Athens', 'Lisbon', 'Warsaw', 'Budapest', 'Prague', 'Buenos Aires', 'Santiago', 'Lima'
];

export const STUDIO_CATEGORY_MAP: Record<string, string[]> = {
  'EKONOMI': ['Ekonomi', 'Bisnis', 'Investasi', 'Ketenagakerjaan'],
  'SAINTEK': ['Pendidikan', 'Teknologi'],
  'LINGKUNGAN': ['Lingkungan'],
  'POLITIK': ['Kebijakan', 'Nasional', 'Asean', 'Internasional'],
  'HUKUM': ['Hukum', 'Kriminal', 'Investigasi'],
  'SOSIAL': ['Sosial', 'Budaya', 'Gastronomi', 'Seni & Sastra', 'Sejarah'],
  'LIFESTYLE': ['Religi', 'Olahraga', 'Hiburan', 'Kesehatan', 'Musik & Film'],
};

export const ArticlesManager: React.FC<ArticlesManagerProps> = ({
  articles,
  currentUser,
  galleryItems,
  initialCreateMode = false,
  onNavigateToCreate,
  onSaveArticle,
  onDeleteArticle,
  onStatusChange,
  onToggleSidebar,
  isCollapsed,
  onOpenMobileSidebar,
  onBackToPortal,
}) => {
  const [filterStatus, setFilterStatus] = useState<string>('ALL');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [locationSearchText, setLocationSearchText] = useState<string>('');
  const [showLocationDropdown, setShowLocationDropdown] = useState<boolean>(false);
  const [isEditing, setIsEditing] = useState<boolean>(initialCreateMode);
  const [editingArticle, setEditingArticle] = useState<Partial<Article> | null>(() => {
    if (initialCreateMode) {
      return {
        title: '',
        content: '',
        category: 'EKONOMI',
        subCategory: 'Ekonomi',
        image: 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=1200&q=80',
        caption: '',
        breaking: false,
        featured: false,
        bullets: ['', '', '', ''],
        author: currentUser.name,
        authorEmail: currentUser.email,
        editor: 'Tim Redaksi GNN',
        location: 'Jakarta, GNN',
        status: currentUser.role === 'sahabat' ? 'draft' : 'publish',
      };
    }
    return null;
  });
  const [saving, setSaving] = useState<boolean>(false);
  const [isCropperOpen, setIsCropperOpen] = useState<boolean>(false);
  const [cropperAspect, setCropperAspect] = useState<AspectRatioType>('5:4');
  const [showGalleryPickerModal, setShowGalleryPickerModal] = useState<boolean>(false);
  const [keywordsInput, setKeywordsInput] = useState<string>(
    editingArticle?.keywords ? editingArticle.keywords.join(', ') : ''
  );

  React.useEffect(() => {
    if (editingArticle?.keywords) {
      setKeywordsInput(editingArticle.keywords.join(', '));
    } else {
      setKeywordsInput('');
    }
  }, [editingArticle?.id]);

  // Filter articles based on Role & Status filter
  const visibleArticles = articles.filter((art) => {
    // Role filter: Sahabat can only see their own articles
    if (currentUser.role === 'sahabat') {
      const isOwner =
        art.authorEmail === currentUser.email ||
        art.author.toLowerCase().includes(currentUser.name.toLowerCase()) ||
        art.author.toLowerCase().includes(currentUser.username.toLowerCase());
      if (!isOwner) return false;
    }

    // Status filter
    if (filterStatus !== 'ALL' && art.status !== filterStatus) {
      return false;
    }

    // Search query
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      return (
        art.title.toLowerCase().includes(q) ||
        art.category.toLowerCase().includes(q) ||
        art.author.toLowerCase().includes(q)
      );
    }

    return true;
  });

  const handleOpenCreate = () => {
    if (onNavigateToCreate) {
      onNavigateToCreate();
      return;
    }
    setEditingArticle({
      title: '',
      content: '',
      category: 'EKONOMI',
      subCategory: 'Ekonomi',
      image: 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=1200&q=80',
      caption: '',
      breaking: false,
      featured: false,
      bullets: ['', '', '', ''],
      author: currentUser.name,
      authorEmail: currentUser.email,
      editor: 'Tim Redaksi GNN',
      location: 'Jakarta, GNN',
      status: currentUser.role === 'sahabat' ? 'draft' : 'publish',
    });
    setIsEditing(true);
  };

  const handleOpenEdit = (art: Article) => {
    let cat = (art.category || 'EKONOMI').toUpperCase();
    if (!STUDIO_CATEGORY_MAP[cat]) {
      // Map any legacy categories if any
      cat = cat === 'GAYA HIDUP' ? 'LIFESTYLE' : 'EKONOMI';
    }
    const availableSubs = STUDIO_CATEGORY_MAP[cat] || STUDIO_CATEGORY_MAP['EKONOMI'];
    let subCat = art.subCategory || availableSubs[0];
    if (!availableSubs.includes(subCat)) {
      subCat = availableSubs[0];
    }

    setEditingArticle({
      ...art,
      category: cat,
      subCategory: subCat,
    });
    setIsEditing(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingArticle?.title || !editingArticle?.content) {
      alert('Judul dan Konten Artikel wajib diisi!');
      return;
    }

    setSaving(true);
    try {
      await onSaveArticle(editingArticle);
      setIsEditing(false);
      setEditingArticle(null);
    } catch (err: any) {
      alert('Gagal menyimpan artikel: ' + err.message);
    } finally {
      setSaving(false);
    }
  };

  const getStatusBadge = (status?: ArticleStatus) => {
    switch (status) {
      case 'publish':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" /> Published
          </span>
        );
      case 'review':
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 flex items-center gap-1">
            <Clock className="w-3.5 h-3.5 text-blue-600" /> Review
          </span>
        );
      case 'draft':
      default:
        return (
          <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1">
            <FileText className="w-3.5 h-3.5 text-amber-600" /> Draft
          </span>
        );
    }
  };

  return (
    <div className="space-y-4">
      {/* 1 TRULY UNIFIED SINGLE-LINE HEADER BAR */}
      <div className="sticky top-0 z-30 -mx-4 sm:-mx-6 mb-6 min-h-[57px] py-2 bg-white px-4 sm:px-6 border-b border-zinc-200 shadow-2xs flex flex-wrap sm:flex-nowrap items-center justify-between gap-3 shrink-0 overflow-x-hidden">
        {/* Left Side: Sidebar Toggle & Page Title */}
        <div className="flex items-center gap-2.5 shrink-0 min-w-0">
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
              <FileText className="w-5 h-5 text-red-600" />
            </button>
          )}

          <h2 className="text-sm sm:text-base font-black text-zinc-900 tracking-tight whitespace-nowrap truncate">
            {initialCreateMode ? 'Tulis Artikel Baru' : 'Manajemen Artikel'}
          </h2>
        </div>

        {/* Right Side: Search, Filter Publikasi, Tombol Buat Artikel (Hanya di Halaman Daftar Artikel) */}
        {!initialCreateMode && !isEditing && (
          <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full sm:w-auto justify-end min-w-0">
            {/* Search Input (Pencarian judul / nama penulis / kategori) */}
            <div className="relative flex-1 min-w-[100px] max-w-full sm:w-44">
              <Search className="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Cari..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full bg-zinc-50 border border-zinc-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-zinc-900 focus:outline-none focus:border-red-600 focus:bg-white transition"
              />
            </div>

            {/* Dropdown Filter Status Artikel */}
            <div className="relative shrink-0">
              <select
                value={filterStatus}
                onChange={(e) => setFilterStatus(e.target.value)}
                className="appearance-none bg-zinc-50 border border-zinc-200 rounded-xl pl-3 pr-8 py-1.5 text-xs font-bold text-zinc-700 hover:border-zinc-300 focus:outline-none focus:border-red-600 focus:bg-white transition cursor-pointer"
              >
                <option value="ALL">Semua Status</option>
                <option value="draft">Status: Draft</option>
                <option value="review">Status: Review</option>
                <option value="publish">Status: Publish</option>
              </select>
              <ChevronDown className="w-3.5 h-3.5 text-zinc-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>

            {/* Tombol Buat Artikel */}
            <button
              onClick={handleOpenCreate}
              className="px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-2xs transition shrink-0 cursor-pointer whitespace-nowrap"
            >
              <Plus className="w-4 h-4" /> Buat Artikel
            </button>
          </div>
        )}
      </div>

      {/* Standalone Halaman Penulisan / Edit Artikel */}
      {isEditing && editingArticle && (
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-xl space-y-6">
          <div className="flex flex-wrap items-center justify-between border-b border-zinc-100 pb-4 gap-3">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => {
                  if (initialCreateMode) {
                    window.location.hash = '#/studio/artikel';
                  } else {
                    setIsEditing(false);
                    setEditingArticle(null);
                  }
                }}
                className="px-3.5 py-2 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
              >
                <ArrowLeft className="w-4 h-4 text-zinc-600" /> Kembali ke Daftar Artikel
              </button>
              <h3 className="text-base sm:text-lg font-extrabold text-zinc-900 flex items-center gap-2">
                <Edit className="w-5 h-5 text-red-600" />
                {editingArticle.id ? 'Edit Artikel' : 'Tulis Artikel Baru'}
              </h3>
            </div>
            <button
              type="button"
              onClick={() => {
                if (initialCreateMode) {
                  window.location.hash = '#/studio/artikel';
                } else {
                  setIsEditing(false);
                  setEditingArticle(null);
                }
              }}
              className="px-3 py-1.5 text-zinc-500 hover:text-zinc-900 text-xs font-bold rounded-xl bg-zinc-100 hover:bg-zinc-200 transition cursor-pointer"
            >
              Batal & Tutup
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            {/* JUDUL ARTIKEL */}
            <div className="space-y-1.5">
              <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700">
                Judul Artikel *
              </label>
              <input
                type="text"
                required
                placeholder="Masukkan judul artikel yang menarik dan inspiratif..."
                value={editingArticle.title || ''}
                onChange={(e) =>
                  setEditingArticle((prev) => ({ ...prev, title: e.target.value }))
                }
                className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-sm text-zinc-900 font-bold focus:border-red-600 focus:bg-white focus:outline-none transition shadow-2xs"
              />
            </div>

            {/* KATEGORI DAN SUB KATEGORI (100% SESUAI NAVIBAR HEADER WEB) */}
            {(() => {
              const selectedCat = (editingArticle.category || 'EKONOMI').toUpperCase();
              const currentSubMap = STUDIO_CATEGORY_MAP[selectedCat] || STUDIO_CATEGORY_MAP['EKONOMI'];

              return (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="space-y-1.5">
                    <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700">
                      Kategori Artikel *
                    </label>
                    <select
                      value={selectedCat}
                      onChange={(e) => {
                        const newCat = e.target.value;
                        const defaultSub = STUDIO_CATEGORY_MAP[newCat][0];
                        setEditingArticle((prev) => ({
                          ...prev,
                          category: newCat,
                          subCategory: defaultSub,
                        }));
                      }}
                      className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs font-bold text-zinc-800 focus:border-red-600 focus:bg-white focus:outline-none transition cursor-pointer"
                    >
                      <option value="EKONOMI">Ekonomi</option>
                      <option value="SAINTEK">Saintek</option>
                      <option value="LINGKUNGAN">Lingkungan</option>
                      <option value="POLITIK">Politik</option>
                      <option value="HUKUM">Hukum</option>
                      <option value="SOSIAL">Sosial</option>
                      <option value="LIFESTYLE">Lifestyle</option>
                    </select>
                  </div>

                  <div className="space-y-1.5">
                    <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700">
                      Sub Kategori (Opsional)
                    </label>
                    <select
                      value={editingArticle.subCategory || currentSubMap[0]}
                      onChange={(e) =>
                        setEditingArticle((prev) => ({ ...prev, subCategory: e.target.value }))
                      }
                      className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 font-medium focus:border-red-600 focus:bg-white focus:outline-none transition cursor-pointer"
                    >
                      {currentSubMap.map((sub) => (
                        <option key={sub} value={sub}>
                          {sub}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              );
            })()}

            {/* LOKASI PELIPUTAN / BERITA (INPUT PENCARIAN DENGAN DROPDOWN OPSI DI BAWAHNYA) */}
            <div className="space-y-1.5 relative">
              <div className="flex items-center justify-between">
                <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700">
                  Lokasi Peliputan / Berita *
                </label>
                <span className="text-[10px] font-semibold text-zinc-400">
                  Otomasis menjadi pembuka di awal isi artikel
                </span>
              </div>

              {(() => {
                // Map of city/kabupaten to Province
                const getProvince = (cityName: string): string => {
                  const lower = cityName.toLowerCase();
                  if (lower.includes('jakarta') || lower.includes('seribu')) return 'DKI Jakarta';
                  if (lower.includes('surabaya') || lower.includes('malang') || lower.includes('kediri') || lower.includes('madiun') || lower.includes('blitar') || lower.includes('pasuruan') || lower.includes('probolinggo') || lower.includes('batu') || lower.includes('banyuwangi') || lower.includes('jember') || lower.includes('sidoarjo') || lower.includes('gresik') || lower.includes('tuban') || lower.includes('lamongan') || lower.includes('bojongeoro') || lower.includes('jombang') || lower.includes('ponorogo') || lower.includes('ngawi') || lower.includes('pacitan') || lower.includes('sampang') || lower.includes('pamekasan') || lower.includes('sumenep') || lower.includes('bangkalan') || lower.includes('lumajang') || lower.includes('bondowoso') || lower.includes('situbondo') || lower.includes('trenggalek') || lower.includes('tulungagung')) return 'Jawa Timur';
                  if (lower.includes('bandung') || lower.includes('cirebon') || lower.includes('sukabumi') || lower.includes('tasikmalaya') || lower.includes('banjar') || lower.includes('cimahi') || lower.includes('depok') || lower.includes('bogor') || lower.includes('bekasi') || lower.includes('garut') || lower.includes('cianjur') || lower.includes('indramayu') || lower.includes('karawang') || lower.includes('kuningan') || lower.includes('majalengka') || lower.includes('pangandaran') || lower.includes('purwakarta') || lower.includes('subang') || lower.includes('sumedang')) return 'Jawa Barat';
                  if (lower.includes('semarang') || lower.includes('surakarta') || lower.includes('solo') || lower.includes('magelang') || lower.includes('salatiga') || lower.includes('tegal') || lower.includes('pekalongan') || lower.includes('banjarnegara') || lower.includes('banyumas') || lower.includes('batang') || lower.includes('blora') || lower.includes('boyolali') || lower.includes('brebes') || lower.includes('cilacap') || lower.includes('demak') || lower.includes('grobogan') || lower.includes('jepara') || lower.includes('karanganyar') || lower.includes('kebumen') || lower.includes('kendal') || lower.includes('klaten') || lower.includes('kudus') || lower.includes('pati') || lower.includes('pemalang') || lower.includes('purbalingga') || lower.includes('purworejo') || lower.includes('rembang') || lower.includes('sragen') || lower.includes('sukoharjo') || lower.includes('temanggung') || lower.includes('wonogiri') || lower.includes('wonosobo')) return 'Jawa Tengah';
                  if (lower.includes('yogyakarta') || lower.includes('bantul') || lower.includes('gunungkidul') || lower.includes('kulon progo') || lower.includes('sleman')) return 'DI Yogyakarta';
                  if (lower.includes('serang') || lower.includes('cilegon') || lower.includes('tangerang') || lower.includes('lebak') || lower.includes('pandeglang')) return 'Banten';
                  if (lower.includes('denpasar') || lower.includes('badung') || lower.includes('bangli') || lower.includes('buleleng') || lower.includes('gianyar') || lower.includes('jembrana') || lower.includes('karangasem') || lower.includes('klungkung') || lower.includes('tabanan')) return 'Bali';
                  if (lower.includes('mataram') || lower.includes('bima') || lower.includes('dompu') || lower.includes('lombok') || lower.includes('sumbawa')) return 'Nusa Tenggara Barat';
                  if (lower.includes('kupang') || lower.includes('alor') || lower.includes('belu') || lower.includes('ende') || lower.includes('flores') || lower.includes('lembata') || lower.includes('manggarai') || lower.includes('sikka') || lower.includes('sumba') || lower.includes('timor')) return 'Nusa Tenggara Timur';
                  if (lower.includes('medan') || lower.includes('binjai') || lower.includes('pematangsiantar') || lower.includes('sibolga') || lower.includes('tanjungbalai') || lower.includes('tebing tinggi') || lower.includes('padangsidimpuan') || lower.includes('gunungsitoli') || lower.includes('deli serdang') || lower.includes('karo') || lower.includes('langkat') || lower.includes('simalungun') || lower.includes('asahan') || lower.includes('tapanuli') || lower.includes('nias')) return 'Sumatera Utara';
                  if (lower.includes('padang') || lower.includes('bukittinggi') || lower.includes('solok') || lower.includes('sawahlunto') || lower.includes('payakumbuh') || lower.includes('pariaman') || lower.includes('agam') || lower.includes('mentawai') || lower.includes('pasaman') || lower.includes('pesisir')) return 'Sumatera Barat';
                  if (lower.includes('pekanbaru') || lower.includes('dumai') || lower.includes('bengkalis') || lower.includes('indragiri') || lower.includes('kampar') || lower.includes('pelalawan') || lower.includes('rokan') || lower.includes('siak')) return 'Riau';
                  if (lower.includes('batam') || lower.includes('tanjungpinang') || lower.includes('bintan') || lower.includes('karimun') || lower.includes('anambas') || lower.includes('natuna')) return 'Kepulauan Riau';
                  if (lower.includes('palembang') || lower.includes('prabumulih') || lower.includes('pagar alam') || lower.includes('lubuklinggau') || lower.includes('banyuasin') || lower.includes('lahat') || lower.includes('muara enim') || lower.includes('musi') || lower.includes('ogan')) return 'Sumatera Selatan';
                  if (lower.includes('bandar lampung') || lower.includes('metro') || lower.includes('lampung') || lower.includes('mesuji') || lower.includes('pesawaran') || lower.includes('pringsewu') || lower.includes('tanggamus') || lower.includes('tulang bawang') || lower.includes('way kanan')) return 'Lampung';
                  if (lower.includes('pontianak') || lower.includes('singkawang') || lower.includes('bengkayang') || lower.includes('ketapang') || lower.includes('kubu raya') || lower.includes('sambas') || lower.includes('sintang')) return 'Kalimantan Barat';
                  if (lower.includes('palangkaraya') || lower.includes('barito') || lower.includes('kapuas') || lower.includes('katingan') || lower.includes('kotawaringin')) return 'Kalimantan Tengah';
                  if (lower.includes('banjarmasin') || lower.includes('banjarbaru') || lower.includes('banjar') || lower.includes('hulu sungai') || lower.includes('kotabaru') || lower.includes('tabalong') || lower.includes('tanah laut')) return 'Kalimantan Selatan';
                  if (lower.includes('samarinda') || lower.includes('balikpapan') || lower.includes('bontang') || lower.includes('ikn') || lower.includes('kutai') || lower.includes('berau') || lower.includes('paser')) return 'Kalimantan Timur';
                  if (lower.includes('makassar') || lower.includes('parepare') || lower.includes('palopo') || lower.includes('bantaeng') || lower.includes('bone') || lower.includes('bulukumba') || lower.includes('gowa') || lower.includes('jeneponto') || lower.includes('luwu') || lower.includes('maros') || lower.includes('toraja') || lower.includes('wajo')) return 'Sulawesi Selatan';
                  if (lower.includes('manado') || lower.includes('bitung') || lower.includes('tomohon') || lower.includes('kotamobagu') || lower.includes('minahasa') || lower.includes('sangihe') || lower.includes('talaud')) return 'Sulawesi Utara';
                  if (lower.includes('palu') || lower.includes('banggai') || lower.includes('buol') || lower.includes('donggala') || lower.includes('morowali') || lower.includes('poso') || lower.includes('tolitoli')) return 'Sulawesi Tengah';
                  if (lower.includes('kendari') || lower.includes('baubau') || lower.includes('buton') || lower.includes('kolaka') || lower.includes('konawe') || lower.includes('muna') || lower.includes('wakatobi')) return 'Sulawesi Tenggara';
                  if (lower.includes('ambon') || lower.includes('tual') || lower.includes('buru') || lower.includes('aru') || lower.includes('tanimbar') || lower.includes('maluku tengah') || lower.includes('seram')) return 'Maluku';
                  if (lower.includes('ternate') || lower.includes('tidore') || lower.includes('sofifi') || lower.includes('halmahera') || lower.includes('sula') || lower.includes('morotai')) return 'Maluku Utara';
                  if (lower.includes('jayapura') || lower.includes('sorong') || lower.includes('manokwari') || lower.includes('merauke') || lower.includes('nabire') || lower.includes('wamena') || lower.includes('timika') || lower.includes('biak') || lower.includes('mimika') || lower.includes('asmat') || lower.includes('raja ampat')) return 'Papua';
                  return 'Indonesia';
                };

                const cleanCityName = (name: string) =>
                  name.replace(/^Kab\.\s*/i, '').replace(/^Kota\s*/i, '').trim();

                const ALL_LOCATIONS = [
                  ...INDONESIAN_CITIES.map((c) => {
                    const clean = cleanCityName(c);
                    const prov = getProvince(c);
                    return {
                      cityName: clean,
                      dropdownLabel: `${clean}, ${prov}, Indonesia`,
                      fullFormat: `${clean}, GNN`,
                    };
                  }),
                  ...WORLD_CAPITALS.map((w) => {
                    const clean = cleanCityName(w);
                    return {
                      cityName: clean,
                      dropdownLabel: `${clean}, Ibukota Dunia`,
                      fullFormat: `${clean}, GNN`,
                    };
                  }),
                ];

                const currentLocation = editingArticle.location || '';
                const filteredLocations = locationSearchText.trim()
                  ? ALL_LOCATIONS.filter((loc) =>
                      loc.cityName.toLowerCase().includes(locationSearchText.toLowerCase()) ||
                      loc.dropdownLabel.toLowerCase().includes(locationSearchText.toLowerCase())
                    )
                  : ALL_LOCATIONS.slice(0, 20);

                return (
                  <div className="relative">
                    <div className="relative">
                      <Search className="w-4 h-4 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2" />
                      <input
                        type="text"
                        required
                        placeholder="Ketik nama kota (misal: Malang, Surabaya, Bandung)..."
                        value={currentLocation}
                        onFocus={() => setShowLocationDropdown(true)}
                        onBlur={() => setTimeout(() => setShowLocationDropdown(false), 200)}
                        onChange={(e) => {
                          const val = e.target.value;
                          setLocationSearchText(val);
                          setEditingArticle((prev) => ({ ...prev, location: val }));
                          setShowLocationDropdown(true);
                        }}
                        className="w-full bg-zinc-50 border border-zinc-200 rounded-xl pl-9 pr-4 py-2.5 text-xs text-zinc-900 font-bold focus:border-red-600 focus:bg-white focus:outline-none transition shadow-2xs"
                      />
                    </div>

                    {/* Autocomplete Dropdown list below search field */}
                    {showLocationDropdown && filteredLocations.length > 0 && (
                      <div className="absolute top-full left-0 right-0 mt-1 max-h-56 overflow-y-auto bg-white border border-zinc-200 rounded-xl shadow-xl z-50 divide-y divide-zinc-100">
                        {filteredLocations.map((loc, idx) => (
                          <button
                            key={`${loc.fullFormat}-${idx}`}
                            type="button"
                            onMouseDown={(e) => {
                              e.preventDefault();
                              setEditingArticle((prev) => ({ ...prev, location: loc.fullFormat }));
                              setLocationSearchText('');
                              setShowLocationDropdown(false);
                            }}
                            className="w-full text-left px-4 py-2.5 text-xs font-bold text-zinc-800 hover:bg-red-50 hover:text-red-700 transition flex items-center justify-between cursor-pointer"
                          >
                            <span>{loc.dropdownLabel}</span>
                            {currentLocation === loc.fullFormat && (
                              <span className="text-[10px] font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded-full">
                                Terpilih
                              </span>
                            )}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })()}
            </div>

            {/* FOTO COVER */}
            <div className="space-y-3 bg-zinc-50/70 border border-zinc-200 p-4 rounded-2xl">
              <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700 block">
                Foto Cover Artikel *
              </label>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                {/* SISI KIRI: GAMBAR COVER (PREVIEW & CLICK TO CROP) */}
                <div className="md:col-span-1">
                  <div className="w-full h-44 rounded-2xl overflow-hidden bg-zinc-200 border border-zinc-300 relative shadow-2xs flex items-center justify-center group">
                    {editingArticle.image ? (
                      <>
                        <img
                          src={editingArticle.image}
                          alt="Cover Preview"
                          className="w-full h-full object-cover"
                        />
                        <button
                          type="button"
                          onClick={() => setIsCropperOpen(true)}
                          className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex flex-col items-center justify-center text-white text-xs font-bold gap-1 cursor-pointer"
                        >
                          <Crop className="w-5 h-5 text-white" />
                          <span>Potong & Atur Foto</span>
                        </button>
                      </>
                    ) : (
                      <div className="w-full h-full flex flex-col items-center justify-center text-zinc-400 p-4 text-center">
                        <ImageIcon className="w-8 h-8 mb-1.5 text-zinc-400" />
                        <span className="text-xs font-bold text-zinc-500">Belum Ada Foto Cover</span>
                        <span className="text-[10px] text-zinc-400">Klik Unggah atau Pilih Galeri di sebelah kanan</span>
                      </div>
                    )}
                  </div>
                </div>

                {/* SISI KANAN: OPSI UNGGAH, GALERI, POTONG & FORM INPUT CAPTION & KREDIT */}
                <div className="md:col-span-2 space-y-3">
                  {/* Row 1: Tombol Unggah & Tombol Galeri */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {/* Opsi 1: Tombol Unggah Gambar */}
                    <label className="px-4 py-3 rounded-2xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-extrabold transition cursor-pointer flex items-center justify-center gap-2 shadow-sm text-center">
                      <Upload className="w-4 h-4 text-red-400" /> Unggah Gambar Baru
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={async (e) => {
                          const file = e.target.files?.[0];
                          if (file) {
                            try {
                              const res = await compressImageToUnder50KB(file);
                              const fileNameClean = file.name.replace(/\.[^/.]+$/, '');
                              setEditingArticle((prev) => ({
                                ...prev,
                                image: res.dataUrl,
                                caption: prev?.caption || fileNameClean,
                                imageCredit: prev?.imageCredit || 'Tim Redaksi GNN',
                              }));
                              setCropperAspect('5:4');
                              setIsCropperOpen(true);
                            } catch (err) {
                              alert('Gagal mengompres gambar cover');
                            }
                          }
                        }}
                      />
                    </label>

                    {/* Opsi 2: Tombol Galeri Foto Redaksi */}
                    <button
                      type="button"
                      onClick={() => setShowGalleryPickerModal(true)}
                      className="px-4 py-3 rounded-2xl bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold transition cursor-pointer flex items-center justify-center gap-2 shadow-sm text-center"
                    >
                      <ImageIcon className="w-4 h-4" /> Pilih dari Galeri Foto
                    </button>
                  </div>

                  {/* Tombol Opsi Potong & Atur Rasio Foto (jika ada gambar) */}
                  {editingArticle.image && (
                    <button
                      type="button"
                      onClick={() => setIsCropperOpen(true)}
                      className="w-full px-4 py-2.5 rounded-xl bg-white hover:bg-zinc-100 text-zinc-800 text-xs font-extrabold transition cursor-pointer flex items-center justify-center gap-2 border border-zinc-300 shadow-2xs"
                    >
                      <Crop className="w-4 h-4 text-red-600" /> Potong, Atur Rasio, Judul & Kredit Foto
                    </button>
                  )}

                  {/* Row 2: EDITABLE Form Input Keterangan Identitas Foto (Nama/Caption) & Kredit Foto */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-zinc-200">
                    <div className="space-y-1">
                      <label className="text-[11px] font-extrabold uppercase tracking-wider text-zinc-700 flex items-center gap-1">
                        <Tag className="w-3.5 h-3.5 text-red-600" /> Nama / Judul Foto (Caption)
                      </label>
                      <input
                        type="text"
                        placeholder="Contoh: Pemandangan Gunung Bromo pagi hari"
                        value={editingArticle.caption || ''}
                        onChange={(e) =>
                          setEditingArticle((prev) => (prev ? { ...prev, caption: e.target.value } : null))
                        }
                        className="w-full bg-white border border-zinc-300 rounded-xl px-3 py-2 text-xs text-zinc-900 font-medium focus:border-red-600 focus:outline-none transition shadow-2xs"
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-[11px] font-extrabold uppercase tracking-wider text-zinc-700 flex items-center gap-1">
                        <UserCheck className="w-3.5 h-3.5 text-red-600" /> Kredit Foto (Fotografer / Sumber)
                      </label>
                      <input
                        type="text"
                        placeholder="Contoh: Foto: Antara / Tim Redaksi GNN"
                        value={editingArticle.imageCredit || ''}
                        onChange={(e) =>
                          setEditingArticle((prev) => (prev ? { ...prev, imageCredit: e.target.value } : null))
                        }
                        className="w-full bg-white border border-zinc-300 rounded-xl px-3 py-2 text-xs text-zinc-900 font-medium focus:border-red-600 focus:outline-none transition shadow-2xs"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* ISI ARTIKEL */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700">
                  Isi Artikel *
                </label>
                <span className="text-[11px] font-semibold text-red-600">
                  (Lokasi: {editingArticle.location || 'Jakarta, GNN'})
                </span>
              </div>
              <WysiwygEditor
                content={editingArticle.content || ''}
                onChange={(html) =>
                  setEditingArticle((prev) => ({ ...prev, content: html }))
                }
              />
            </div>

            {/* KATA KUNCI */}
            <div className="space-y-1.5">
              <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-700 flex items-center gap-1.5">
                <Tag className="w-3.5 h-3.5 text-red-600" />
                Kata Kunci / Tag (Pisahkan dengan koma)
              </label>
              <input
                type="text"
                placeholder="Contoh: bromo, pariwisata, jawa timur, inspirasi indonesia"
                value={keywordsInput}
                onChange={(e) => {
                  const val = e.target.value;
                  setKeywordsInput(val);
                  const kwArray = val
                    .split(',')
                    .map((s) => s.trim())
                    .filter(Boolean);
                  setEditingArticle((prev) => (prev ? { ...prev, keywords: kwArray } : null));
                }}
                className="w-full bg-zinc-50 border border-zinc-200 rounded-xl px-4 py-2.5 text-xs text-zinc-900 font-medium focus:border-red-600 focus:bg-white focus:outline-none transition shadow-2xs"
              />
            </div>

            {/* TOMBOL PENERBITAN */}
            <div className="bg-zinc-50 border border-zinc-200 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="space-y-0.5 text-left w-full sm:w-auto">
                <label className="text-xs font-extrabold uppercase tracking-wider text-zinc-800 block">
                  Status Penerbitan
                </label>
                <span className="text-xs text-zinc-500 font-medium">
                  Anda masuk sebagai <strong className="text-zinc-900 capitalize">{currentUser.role}</strong> ({currentUser.name})
                </span>
              </div>

              <div className="flex items-center gap-3 w-full sm:w-auto justify-end">
                <select
                  value={editingArticle.status || 'draft'}
                  onChange={(e) =>
                    setEditingArticle((prev) => ({
                      ...prev,
                      status: e.target.value as ArticleStatus,
                    }))
                  }
                  disabled={currentUser.role === 'sahabat'}
                  className="bg-white border border-zinc-200 rounded-xl px-3 py-2 text-xs font-bold text-zinc-800 focus:border-red-600 focus:outline-none transition disabled:opacity-60"
                >
                  <option value="draft">Konsep (Draft)</option>
                  <option value="review">Menunggu Review</option>
                  {(currentUser.role === 'admin' || currentUser.role === 'reviewer') && (
                    <option value="publish">Terbitkan (Publish)</option>
                  )}
                </select>

                <button
                  type="button"
                  onClick={() => {
                    if (initialCreateMode) {
                      window.location.hash = '#/studio/artikel';
                    } else {
                      setIsEditing(false);
                    }
                  }}
                  className="px-4 py-2 rounded-xl text-xs font-bold text-zinc-600 bg-zinc-200 hover:bg-zinc-300 transition cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="px-6 py-2 rounded-xl text-xs font-extrabold text-white bg-red-600 hover:bg-red-700 shadow-md transition disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
                >
                  {saving ? (
                    'Menyimpan...'
                  ) : editingArticle.status === 'publish' ? (
                    <>
                      <CheckCircle2 className="w-4 h-4" /> Terbitkan Artikel
                    </>
                  ) : editingArticle.status === 'review' ? (
                    <>
                      <Send className="w-4 h-4" /> Ajukan Review
                    </>
                  ) : (
                    <>
                      <FileText className="w-4 h-4" /> Simpan Draft
                    </>
                  )}
                </button>
              </div>
            </div>
          </form>
        </div>
      )}

      {/* Articles List (Hanya ditampilkan jika BUKAN sedang menulis/mengedit artikel) */}
      {!initialCreateMode && !isEditing && (
        <div className="space-y-3">
          {visibleArticles.length === 0 ? (
            <div className="bg-white rounded-2xl border border-zinc-200 p-12 text-center text-zinc-400 space-y-2">
              <AlertCircle className="w-8 h-8 text-zinc-300 mx-auto" />
              <p className="text-sm font-semibold">Tidak ada artikel yang ditemukan.</p>
            </div>
          ) : (
            visibleArticles.slice(0, 25).map((art) => (
              <div
                key={art.id}
                className="bg-white rounded-2xl border border-zinc-200 p-4 shadow-2xs hover:border-zinc-300 transition flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
              >
                {/* Foto Cover, Judul, Penulis, Status, Tanggal */}
                <div className="flex items-center gap-4 min-w-0 flex-1">
                  <img
                    src={art.image}
                    alt={art.title}
                    className="w-16 h-16 rounded-xl object-cover shrink-0 border border-zinc-100 shadow-2xs"
                  />
                  <div className="space-y-1 min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                      {getStatusBadge(art.status)}
                      <span className="text-xs text-zinc-500 font-medium">
                        Oleh: <strong className="text-zinc-800 font-bold">{art.author}</strong>
                      </span>
                      <span className="text-zinc-300">•</span>
                      <span className="text-xs text-zinc-400 font-medium">{art.date}</span>
                    </div>

                    <h3 className="text-sm sm:text-base font-bold text-zinc-900 hover:text-red-600 transition leading-snug truncate">
                      {art.title}
                    </h3>
                  </div>
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-2 w-full sm:w-auto justify-end border-t sm:border-t-0 border-zinc-100 pt-3 sm:pt-0 shrink-0">
                  {/* Status Quick Toggle (for Reviewer & Admin) */}
                  {(currentUser.role === 'admin' || currentUser.role === 'reviewer') && (
                    <div className="flex items-center gap-1.5">
                      {art.status !== 'publish' && (
                        <button
                          onClick={() => onStatusChange(art.id, 'publish')}
                          className="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200 flex items-center gap-1 transition cursor-pointer"
                          title="Terbitkan Artikel"
                        >
                          <CheckCircle2 className="w-3.5 h-3.5" /> Publish
                        </button>
                      )}
                      {art.status !== 'review' && (
                        <button
                          onClick={() => onStatusChange(art.id, 'review')}
                          className="px-2.5 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold border border-blue-200 flex items-center gap-1 transition cursor-pointer"
                          title="Ke Review"
                        >
                          <Clock className="w-3.5 h-3.5" /> Review
                        </button>
                      )}
                      {art.status !== 'draft' && (
                        <button
                          onClick={() => onStatusChange(art.id, 'draft')}
                          className="px-2.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold border border-amber-200 flex items-center gap-1 transition cursor-pointer"
                          title="Ke Draft"
                        >
                          <FileText className="w-3.5 h-3.5" /> Draft
                        </button>
                      )}
                    </div>
                  )}

                  {/* Sahabat submit to Review button */}
                  {currentUser.role === 'sahabat' && art.status === 'draft' && (
                    <button
                      onClick={() => onStatusChange(art.id, 'review')}
                      className="px-2.5 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold border border-blue-200 flex items-center gap-1 transition cursor-pointer"
                    >
                      <Send className="w-3.5 h-3.5" /> Ajukan Review
                    </button>
                  )}

                  <div className="flex items-center gap-1">
                    <button
                      onClick={() => handleOpenEdit(art)}
                      className="p-1.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 transition cursor-pointer"
                      title="Edit Artikel"
                    >
                      <Edit className="w-4 h-4" />
                    </button>
                    {(currentUser.role === 'admin' ||
                      (currentUser.role === 'sahabat' && art.status === 'draft')) && (
                      <button
                        onClick={() => onDeleteArticle(art.id)}
                        className="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition cursor-pointer"
                        title="Hapus Artikel"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* Pop-Up Modal: Crop Foto, Nama/Caption & Kredit Foto */}
      {editingArticle?.image && (
        <ImageCropperModal
          isOpen={isCropperOpen}
          imageUrl={editingArticle.image}
          initialCaption={editingArticle.caption || ''}
          initialCredit={editingArticle.imageCredit || ''}
          initialAspect={cropperAspect}
          onClose={() => setIsCropperOpen(false)}
          onCropComplete={(croppedUrl, captionVal, creditVal) => {
            setEditingArticle((prev) =>
              prev
                ? {
                    ...prev,
                    image: croppedUrl,
                    caption: captionVal,
                    imageCredit: creditVal,
                  }
                : null
            );
          }}
        />
      )}

      {/* Pop-Up Modal: Pilih dari Galeri Foto Redaksi */}
      {showGalleryPickerModal && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs overflow-y-auto">
          <div className="bg-white rounded-2xl max-w-xl w-full p-5 space-y-4 shadow-2xl">
            <div className="flex items-center justify-between border-b border-zinc-200 pb-3">
              <div className="flex items-center gap-2">
                <ImageIcon className="w-5 h-5 text-red-600" />
                <h3 className="text-sm font-extrabold text-zinc-900">Pilih Foto dari Galeri Redaksi</h3>
              </div>
              <button
                type="button"
                onClick={() => setShowGalleryPickerModal(false)}
                className="p-1 rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition cursor-pointer"
              >
                ✕
              </button>
            </div>

            {galleryItems.length === 0 ? (
              <div className="py-8 text-center text-zinc-400 text-xs font-semibold">
                Belum ada koleksi foto di Galeri Redaksi.
              </div>
            ) : (
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-72 overflow-y-auto p-1">
                {galleryItems.map((g) => (
                  <div
                    key={g.id}
                    onClick={() => {
                      setEditingArticle((prev) => ({
                        ...prev,
                        image: g.imageUrl,
                        caption: prev?.caption || g.title,
                        imageCredit: prev?.imageCredit || g.author || 'Tim Redaksi GNN',
                      }));
                      setShowGalleryPickerModal(false);
                      setCropperAspect('5:4');
                      setIsCropperOpen(true);
                    }}
                    className="group relative h-28 rounded-xl overflow-hidden border border-zinc-200 cursor-pointer shadow-2xs hover:border-red-600 hover:ring-2 hover:ring-red-200 transition"
                  >
                    <img src={g.imageUrl} alt={g.title} className="w-full h-full object-cover group-hover:scale-105 transition duration-200" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-2">
                      <span className="text-[10px] font-bold text-white truncate">{g.title}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="flex justify-end pt-2 border-t border-zinc-100">
              <button
                type="button"
                onClick={() => setShowGalleryPickerModal(false)}
                className="px-4 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-bold transition cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
