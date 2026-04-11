<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            ['genre_name' => 'Pop', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Rock', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Jazz', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Classical', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Hip Hop', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Electronic', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Country', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Blues', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Reggae', 'status' => 1, 'delete' => 0],
            ['genre_name' => 'Folk', 'status' => 1, 'delete' => 0],
        ];

        foreach ($genres as $genre) {
            DB::table('tbl_genre')->insert([
                'genre_name' => $genre['genre_name'],
                'status' => $genre['status'],
                'delete' => $genre['delete'],
                'created_on' => now(),
                'updated_on' => now(),
            ]);
        }
    }
}
