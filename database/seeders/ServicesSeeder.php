<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('services')->delete();
        DB::statement('ALTER TABLE services AUTO_INCREMENT = 1');

        $now = now();

        // Pata category_id kutoka categories table
        $categories = DB::table('categories')->pluck('id','slug');

        $misuko = [
            "Knotless Braids", "Box Braids", "Twist", "Passion Twist", "Spring Twist",
            "Senegalese Twist", "Marley Twist", "Havana Twist", "Goddess Braids", "Fulani Braids",
            "Lemonade Braids", "Cornrows Classic", "Feed-in Cornrows", "Ghana Braids", "Stitch Braids",
            "Tribal Braids", "Bohemian Braids", "Butterfly Locs", "Soft Locs", "Faux Locs",
            "Invisible Locs", "Distressed Locs", "Bantu Knots", "High Pony Braids", "Braided Bun",
            "Braided Bob", "Jumbo Box Braids", "Small Box Braids", "Medium Box Braids", "Micro Braids",
            "Kinky Twist", "Flat Twist", "Two-Strand Twist", "Three-Strand Twist", "Waterfall Braids",
            "Halo Braid", "Crown Braid", "French Braids", "Dutch Braids", "Fishtail Braid",
            "Crochet Braids", "Crochet Twist", "Crochet Locs", "Crochet Curly", "Sisterlocks",
            "Starter Locs", "Interlock Locs", "Retwist Locs", "Loc Style Updo", "Loc Ponytail",
            "Wig Install", "Frontal Install", "Closure Install", "Sew-in Weave", "Quick Weave",
            "Braid & Weave Mix", "Natural Hair Styling", "Blowout + Braid", "Wash & Set", "Wash + Braid",
            "Kids Braids Simple", "Kids Cornrows", "Kids Beads Braids", "Beads + Cornrows", "Heart Design Braids",
            "Zigzag Cornrows", "Spider Braids", "Khaliji Braid Style", "Side Swept Braids", "Half Up Half Down Braids",
            "Braids with Curls", "Curls End Braids", "Goddess Locs", "Knotless with Curls", "Fulani with Curls",
            "Tribal with Curls", "Lemonade with Curls", "Feed-in with Bun", "Stitch + Bun", "Cornrows + Ponytail",
            "Cornrows + Curls", "Braid Designs (Lines)", "Braid Designs (Patterns)", "Braided Updo Bridal", "Bridal Braids Classic",
            "Bridal Braids + Veil", "Traditional Bridal Style", "Simple Office Braids", "Classic Twist Short", "Classic Twist Long",
            "Box Braids Long", "Box Braids Short", "Braids Touch-up", "Braids Removal", "Braids Redo (Front)",
            "Cornrows Touch-up", "Retouch Edges", "Hairline Neat Fix", "Scalp Treatment + Braid", "Protective Style Basic"
        ];

        $makeup = [
            "Makeup Natural", "Makeup Soft Glam", "Makeup Full Glam", "Makeup Bridal",
            "Makeup Photoshoot", "Makeup Evening", "Makeup Graduation", "Makeup Party",
            "Makeup Matte Look", "Makeup Dewy Look", "Makeup Smokey Eyes", "Makeup Nude Look",
            "Makeup Bold Lips", "Makeup HD", "Makeup Airbrush", "Makeup Traditional",
            "Lashes Install", "Eyebrows Shaping", "Eyebrows Tint", "Makeup Touch-up"
        ];

        $kubana = [
            "Kubana Sleek Back", "Kubana Middle Part", "Kubana Side Part", "Kubana Ponytail",
            "Kubana High Ponytail", "Kubana Low Ponytail", "Kubana Bun", "Kubana Top Bun",
            "Kubana Half Up Half Down", "Kubana Wavy Finish", "Kubana Straight Finish", "Kubana Curly Finish",
            "Kubana With Babyhair", "Kubana Edges Fix", "Kubana Gel Finish", "Kubana Mousse Finish",
            "Kubana Bridal", "Kubana Office", "Kubana Quick Style", "Kubana With Clip-ins",
            "Kubana With Weave", "Kubana With Wig", "Kubana Natural Hair", "Kubana Relaxed Hair",
            "Kubana Short Hair", "Kubana Long Hair", "Kubana with Scarf Style", "Kubana Crown Lift",
            "Kubana Side Sweep", "Kubana Protective Finish"
        ];

        $massage = [
            "Massage Relaxation", "Massage Deep Tissue", "Massage Sports", "Massage Prenatal (Gentle)"
        ];

        $all = [
            'misuko' => $misuko,
            'makeup' => $makeup,
            'kubana' => $kubana,
            'massage' => $massage
        ];

        foreach ($all as $slug => $services) {

            $categoryId = $categories[$slug] ?? null;
            if (!$categoryId) continue;

            foreach ($services as $index => $name) {

                DB::table('services')->insert([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'category' => $slug,
                    'slug' => Str::slug($name).'-'.rand(100,999),
                    'short_desc' => null,
                    'image_url' => null,
                    'base_price' => rand(20000,150000),
                    'is_active' => 1,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
