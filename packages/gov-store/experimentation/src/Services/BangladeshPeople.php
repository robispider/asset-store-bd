<?php

namespace GovStore\Experimentation\Services;

class BangladeshPeople
{
    public const PASSWORD = '1234567890';

    private const NAMES = [
        ['Md. Arif', 'Hossain', 'মোঃ আরিফ হোসেন'], ['Nusrat', 'Jahan', 'নুসরাত জাহান'],
        ['Md. Kamrul', 'Islam', 'মোঃ কামরুল ইসলাম'], ['Farzana', 'Akter', 'ফারজানা আক্তার'],
        ['Abdul', 'Karim', 'আব্দুল করিম'], ['Sharmin', 'Sultana', 'শারমিন সুলতানা'],
        ['Md. Rakibul', 'Hasan', 'মোঃ রাকিবুল হাসান'], ['Tahmina', 'Begum', 'তাহমিনা বেগম'],
        ['Md. Saiful', 'Islam', 'মোঃ সাইফুল ইসলাম'], ['Mst. Roksana', 'Parvin', 'মোছাঃ রোকসানা পারভীন'],
        ['Md. Mahmudul', 'Alam', 'মোঃ মাহমুদুল আলম'], ['Sadia', 'Rahman', 'সাদিয়া রহমান'],
        ['Md. Nazmul', 'Haque', 'মোঃ নাজমুল হক'], ['Jannatul', 'Ferdous', 'জান্নাতুল ফেরদৌস'],
        ['Md. Shafiqul', 'Alam', 'মোঃ শফিকুল আলম'], ['Ayesha', 'Siddika', 'আয়েশা সিদ্দিকা'],
        ['Md. Mizanur', 'Rahman', 'মোঃ মিজানুর রহমান'], ['Sumaiya', 'Akter', 'সুমাইয়া আক্তার'],
        ['Md. Tanvir', 'Ahmed', 'মোঃ তানভীর আহমেদ'], ['Fahmida', 'Khatun', 'ফাহমিদা খাতুন'],
        ['Abdur', 'Rahman', 'আব্দুর রহমান'], ['Rumana', 'Yasmin', 'রুমানা ইয়াসমিন'],
        ['Md. Ashraful', 'Islam', 'মোঃ আশরাফুল ইসলাম'], ['Shamima', 'Nasrin', 'শামীমা নাসরিন'],
        ['Md. Rezaul', 'Karim', 'মোঃ রেজাউল করিম'], ['Tasnim', 'Tabassum', 'তাসনিম তাবাসসুম'],
        ['Md. Imran', 'Hossain', 'মোঃ ইমরান হোসেন'], ['Rifat', 'Ara', 'রিফাত আরা'],
        ['Md. Abdullah', 'Al Mamun', 'মোঃ আব্দুল্লাহ আল মামুন'], ['Sanjida', 'Islam', 'সানজিদা ইসলাম'],
        ['Sujit', 'Kumar Das', 'সুজিত কুমার দাস'], ['Ananya', 'Roy', 'অনন্যা রায়'],
        ['Subrata', 'Chakraborty', 'সুব্রত চক্রবর্তী'], ['Purnima', 'Sarkar', 'পূর্ণিমা সরকার'],
        ['Ripon', 'Tripura', 'রিপন ত্রিপুরা'], ['Mitali', 'Chakma', 'মিতালী চাকমা'],
    ];

    public function name(int $index, int $seed = 2026): array
    {
        [$first, $last, $bangla] = self::NAMES[($index + $seed) % count(self::NAMES)];

        return ['first_name' => $first, 'last_name' => $last, 'display_name' => $bangla];
    }

    public function username(array $name, int $number): string
    {
        $englishName = strtolower(preg_replace('/[^a-zA-Z]/', '', $name['first_name'].' '.$name['last_name']));

        return ($englishName ?: 'person').$number;
    }
}
