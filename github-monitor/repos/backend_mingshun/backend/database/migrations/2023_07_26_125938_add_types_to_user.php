<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('type')->nullable();
        });

        foreach(User::all() as $user){
            $flag = false;
            foreach($user->role->pluck('id')->toArray() as $role_id){
                if($role_id != 3 && $role_id != 4){
                    $flag = true;
                    break;
                }
            }
            if(!$flag){
                $user->type = 1;
            }else{
                $user->type = 3;
            }
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            //
        });
    }
};
