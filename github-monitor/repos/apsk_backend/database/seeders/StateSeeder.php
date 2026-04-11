<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\States;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = [
            // Brunei Darussalam
            ['state_code' => 'BN-BE', 'state_name' => 'Belait'],
            ['state_code' => 'BN-BM', 'state_name' => 'Brunei-Muara'],
            ['state_code' => 'BN-TE', 'state_name' => 'Temburong'],
            ['state_code' => 'BN-TU', 'state_name' => 'Tutong'],
            // Cambodia (sample)
            ['state_code' => 'KH-1', 'state_name' => 'Banteay Meanchey'],
            ['state_code' => 'KH-2', 'state_name' => 'Battambang'],
            ['state_code' => 'KH-3', 'state_name' => 'Kampong Cham'],
            ['state_code' => 'KH-4', 'state_name' => 'Kampong Chhnang'],
            ['state_code' => 'KH-12', 'state_name' => 'Phnom Penh'],
            // Indonesia (sample)
            ['state_code' => 'ID-AC', 'state_name' => 'Aceh'],
            ['state_code' => 'ID-BA', 'state_name' => 'Bali'],
            ['state_code' => 'ID-BT', 'state_name' => 'Banten'],
            ['state_code' => 'ID-JK', 'state_name' => 'Jakarta'],
            ['state_code' => 'ID-JB', 'state_name' => 'West Java'],
            // Laos
            ['state_code' => 'LA-AT', 'state_name' => 'Attapeu'],
            ['state_code' => 'LA-BK', 'state_name' => 'Bokeo'],
            ['state_code' => 'LA-BL', 'state_name' => 'Bolikhamxai'],
            ['state_code' => 'LA-CH', 'state_name' => 'Champasak'],
            ['state_code' => 'LA-VT', 'state_name' => 'Vientiane'],
            ['state_code' => 'LA-XI', 'state_name' => 'Xiangkhouang'],
            // Malaysia
            ['state_code' => 'MY-01', 'state_name' => 'Johor'],
            ['state_code' => 'MY-02', 'state_name' => 'Kedah'],
            ['state_code' => 'MY-03', 'state_name' => 'Kelantan'],
            ['state_code' => 'MY-04', 'state_name' => 'Malacca'],
            ['state_code' => 'MY-05', 'state_name' => 'Negeri Sembilan'],
            ['state_code' => 'MY-06', 'state_name' => 'Pahang'],
            ['state_code' => 'MY-07', 'state_name' => 'Penang'],
            ['state_code' => 'MY-08', 'state_name' => 'Perak'],
            ['state_code' => 'MY-09', 'state_name' => 'Perlis'],
            ['state_code' => 'MY-10', 'state_name' => 'Selangor'],
            ['state_code' => 'MY-11', 'state_name' => 'Terengganu'],
            ['state_code' => 'MY-12', 'state_name' => 'Sabah'],
            ['state_code' => 'MY-13', 'state_name' => 'Sarawak'],
            ['state_code' => 'MY-14', 'state_name' => 'Kuala Lumpur'],
            ['state_code' => 'MY-15', 'state_name' => 'Labuan'],
            ['state_code' => 'MY-16', 'state_name' => 'Putrajaya'],
            // Myanmar
            ['state_code' => 'MM-01', 'state_name' => 'Kachin'],
            ['state_code' => 'MM-02', 'state_name' => 'Kayah'],
            ['state_code' => 'MM-03', 'state_name' => 'Kayin'],
            ['state_code' => 'MM-04', 'state_name' => 'Chin'],
            ['state_code' => 'MM-05', 'state_name' => 'Sagaing'],
            ['state_code' => 'MM-06', 'state_name' => 'Tanintharyi'],
            ['state_code' => 'MM-07', 'state_name' => 'Bago'],
            ['state_code' => 'MM-11', 'state_name' => 'Mon'],
            ['state_code' => 'MM-12', 'state_name' => 'Rakhine'],
            ['state_code' => 'MM-13', 'state_name' => 'Yangon'],
            ['state_code' => 'MM-14', 'state_name' => 'Shan'],
            ['state_code' => 'MM-15', 'state_name' => 'Ayeyarwady'],
            ['state_code' => 'MM-16', 'state_name' => 'Mandalay'],
            ['state_code' => 'MM-17', 'state_name' => 'Naypyidaw'],
            // Philippines (sample)
            ['state_code' => 'PH-ABR', 'state_name' => 'Abra'],
            ['state_code' => 'PH-AGN', 'state_name' => 'Agusan del Norte'],
            ['state_code' => 'PH-AKL', 'state_name' => 'Aklan'],
            ['state_code' => 'PH-ALB', 'state_name' => 'Albay'],
            ['state_code' => 'PH-NCR', 'state_name' => 'Metro Manila'],
            // Singapore (optional)
            ['state_code' => 'SG-SG', 'state_name' => 'Singapore'],
            // Thailand (sample)
            ['state_code' => 'TH-10', 'state_name' => 'Bangkok'],
            ['state_code' => 'TH-11', 'state_name' => 'Samut Prakan'],
            ['state_code' => 'TH-12', 'state_name' => 'Nonthaburi'],
            ['state_code' => 'TH-13', 'state_name' => 'Pathum Thani'],
            ['state_code' => 'TH-14', 'state_name' => 'Phra Nakhon Si Ayutthaya'],
            // Timor-Leste
            ['state_code' => 'TL-AL', 'state_name' => 'Aileu'],
            ['state_code' => 'TL-AN', 'state_name' => 'Ainaro'],
            ['state_code' => 'TL-BA', 'state_name' => 'Baucau'],
            ['state_code' => 'TL-CO', 'state_name' => 'Covalima'],
            ['state_code' => 'TL-OE', 'state_name' => 'Oecusse'],
            // Vietnam (sample)
            ['state_code' => 'VN-44', 'state_name' => 'An Giang'],
            ['state_code' => 'VN-43', 'state_name' => 'Ba Ria-Vung Tau'],
            ['state_code' => 'VN-54', 'state_name' => 'Bac Giang'],
            ['state_code' => 'VN-01', 'state_name' => 'Hanoi'],
            ['state_code' => 'VN-13', 'state_name' => 'Ho Chi Minh City'],
        ];

        foreach ($states as $state) {
            DB::table('tbl_states')->insert([
                'state_code' => $state['state_code'],
                'state_name' => $state['state_name'],
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
        }
    }
}
