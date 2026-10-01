import { createClient } from '@supabase/supabase-js';

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL || process.env.VITE_SUPABASE_URL || 'https://bqzcgwpyjanuvaqivpeo.supabase.co';
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY || process.env.VITE_SUPABASE_ANON_KEY || 'sb_publishable_PJY02yK96tsrbpINNdAQoA_w_7CMN8Q';

export const supabase = createClient(supabaseUrl, supabaseAnonKey);
