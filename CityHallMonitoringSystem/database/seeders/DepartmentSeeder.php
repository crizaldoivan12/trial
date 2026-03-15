<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // Official department list for City Hall workflows.
        // Codes are required for document code generation (CH-YYYY-DEPT-XXXX).
        $departments = [
            [
                'name' => 'Office of the City Mayor',
                'office' => 'City Mayor',
                'department_head' => 'Hon. Dennis Felipe C. Hain',
            ],
            [
                'name' => 'Office of the City Mayor - Processing Team',
                'office' => 'Executive Assistant V',
                'department_head' => 'Ms. Rhea B. Retaga',
            ],
            [
                'name' => 'Office of the City Mayor - Social Services',
                'office' => 'Executive Assistant IV',
                'department_head' => 'Ms. Jingle O. Santos',
            ],
            [
                'name' => 'Office of the City Mayor - Administrative Division',
                'office' => 'Executive Assistant IV',
                'department_head' => 'Ms. Bernadette B. Yatco',
            ],
            [
                'name' => 'Office of the City Mayor - Operations Division',
                'office' => 'Executive Assistant II',
                'department_head' => 'Mr. John Anthony A. Africa',
            ],
            [
                'name' => 'Office of the City Vice Mayor',
                'office' => 'City Vice Mayor',
                'department_head' => 'Hon. Jaime Onofre D. Batallones',
            ],
            [
                'name' => 'Office of the Sangguniang Panlungsod Secretariat',
                'office' => 'Secretary to the SP',
                'department_head' => 'Atty. Venus C. Velasco',
            ],
            [
                'name' => 'Office of the City Accountant',
                'office' => 'City Accountant',
                'department_head' => 'Ms. Elenita O. Trinos / Mr. Wernan E. Peralta',
            ],
            [
                'name' => 'Office of the City Administrator',
                'office' => 'Executive Assistant V',
                'department_head' => 'Atty. Dan Rafael H. Palis',
            ],
            [
                'name' => 'Human Resource Management Office (HRMO)',
                'office' => 'HRMO',
                'department_head' => 'Atty. Dan Rafael H. Palis',
            ],
            [
                'name' => 'Office of the City Assessor',
                'office' => 'City Assessor',
                'department_head' => 'Ms. Merlinda R. Villaluna',
            ],
            [
                'name' => 'Business Permit and Licensing Office',
                'office' => 'City Business Permit and Licensing Officer',
                'department_head' => 'Atty. Ryan John C. Cancio',
            ],
            [
                'name' => 'Office of the City Budget',
                'office' => 'City Budget Officer',
                'department_head' => 'Mr. Alfred Benedict E. Suarez / Ms. Maria Nenita M. Paguio',
            ],
            [
                'name' => 'Youth Development Affairs Office',
                'office' => 'OIC - Youth Development Affairs',
                'department_head' => 'Mr. Alfred Benedict E. Suarez',
            ],
            [
                'name' => 'Office of the City Agriculturist',
                'office' => 'City Agriculturist',
                'department_head' => 'Ms. Luzviminda L. Ednalino / Mr. Roderick M. Hernandez',
            ],
            [
                'name' => 'City Cooperatives and Livelihood Office',
                'office' => 'Cooperative & Livelihood Dev. Officer',
                'department_head' => 'Mr. Christian Benedict T. Bueno',
            ],
            [
                'name' => 'Office of the Civil Registrar',
                'office' => 'Civil Registrar',
                'department_head' => 'Ms. Marilyn Diamat',
            ],
            [
                'name' => 'City Disaster Risk Reduction and Management Office (CDRRMO)',
                'office' => 'CDRRMO',
                'department_head' => 'Mr. Colin Garcia',
            ],
            [
                'name' => 'City Environment and Natural Resources Office',
                'office' => 'Environment & Natural Resources Officer',
                'department_head' => 'Engr. Manolo Punongbayan',
            ],
            [
                'name' => 'Office of the City Health Officer I',
                'office' => 'City Health Officer II',
                'department_head' => 'Dr. Elena C. Diamante',
            ],
            [
                'name' => 'Office of the City Health Officer II',
                'office' => 'City Health Officer II',
                'department_head' => 'Dr. Cecil Arnold G. Aguillo',
            ],
            [
                'name' => 'City Information Office',
                'office' => 'Information Officer V',
                'department_head' => 'Mr. Aristotle O. Mane / Ms. Rizalina D. Ebron',
            ],
            [
                'name' => 'Person with Disability Affairs Office (PDAO)',
                'office' => 'PDAO',
                'department_head' => 'Ms. Rizalina D. Ebron',
            ],
            [
                'name' => 'Office of the City Architect',
                'office' => 'City Architect',
                'department_head' => 'Ar. Pablo Ronilo L. Tecson',
            ],
            [
                'name' => 'Office of the City Veterinarian',
                'office' => 'City Veterinarian',
                'department_head' => 'Dr. Irma Alegros',
            ],
            [
                'name' => 'City Planning and Development Office',
                'office' => 'Planning & Development Coordinator',
                'department_head' => 'Enp. Deanna T. Ortiz / Ms. Mylene S. Capiroso',
            ],
            [
                'name' => 'City Legal Office',
                'office' => 'City Legal Officer',
                'department_head' => 'Atty. Ronald Solis',
            ],
            [
                'name' => 'Office of the Special Services',
                'office' => 'Nutrition Officer IV',
                'department_head' => 'Ms. Adoracion Dedicatoria',
            ],
            [
                'name' => 'Office of the City Building Official',
                'office' => 'City Building Official',
                'department_head' => 'Ar. Joven M. Guevarra',
            ],
            [
                'name' => 'Office of the City Engineer',
                'office' => 'City Engineering Office',
                'department_head' => 'Ar. Joven M. Guevarra',
            ],
            [
                'name' => 'Public Employment Service Office (PESO)',
                'office' => 'PESO Manager',
                'department_head' => 'Atty. Jonas Barrinuevo',
            ],
            [
                'name' => 'City Population Office',
                'office' => 'City Population Officer',
                'department_head' => 'Dr. Maria Teresa David',
            ],
            [
                'name' => 'Public Order and Safety Office (POSO)',
                'office' => 'POSO',
                'department_head' => 'Atty. Kristoffer Bryan Salom',
            ],
            [
                'name' => 'POSO - Special Services Division',
                'office' => 'Supervising Administrative Officer',
                'department_head' => 'Mr. Sabi T. Abinal Jr.',
            ],
            [
                'name' => 'POSO - Traffic Management Division',
                'office' => 'Traffic Management Division',
                'department_head' => 'TBD',
            ],
            [
                'name' => 'POSO - Civil Security Unit',
                'office' => 'Civil Security Unit',
                'department_head' => 'TBD',
            ],
            [
                'name' => 'General Services Office',
                'office' => 'OIC - General Services',
                'department_head' => 'Atty. Kristoffer Bryan Salom / Ms. Jovie Mayne H. Legaspi-Valdez',
            ],
            [
                'name' => 'Office of the City Treasurer',
                'office' => 'City Treasurer',
                'department_head' => 'Ms. Jovita Bienes / Mr. Edmund D. Pabale',
            ],
            [
                'name' => 'Office of the City Tourism',
                'office' => 'Tourism Officer',
                'department_head' => 'Ms. Catherine A. Javier',
            ],
            [
                'name' => 'BOTICAB',
                'office' => 'OIC - BOTICAB',
                'department_head' => 'Ms. Princess Completo',
            ],
            [
                'name' => 'Cabuyao City Hospital',
                'office' => 'OIC - Cabuyao City Hospital',
                'department_head' => 'Dr. Gregorio V. Fabros',
            ],
            [
                'name' => 'City Social Welfare and Development Office (CSWD)',
                'office' => 'CSWD',
                'department_head' => 'Ms. Ruby Mel Rebaya',
            ],
            [
                'name' => 'City Slaughterhouse Office',
                'office' => 'Slaughterhouse Master III',
                'department_head' => 'Mr. Felimon L. Victorino',
            ],
            [
                'name' => 'Management Information System (MIS)',
                'office' => 'MIS Department',
                'department_head' => 'Mr. Eleazer Ines',
            ],
            [
                'name' => 'City Urban Development and Housing Affairs Office (CUDHAO)',
                'office' => 'CUDHAO',
                'department_head' => 'Mr. Nathaniel F. Dela',
            ],
            [
                'name' => 'Department of Trade and Industry (DTI)',
                'office' => 'DTI',
                'department_head' => 'Mr. Jerome Laviña',
            ],
            [
                'name' => 'Office of the City Market',
                'office' => 'Market Office',
                'department_head' => 'Mr. Jeffrey Andaya',
            ],
            [
                'name' => 'Office of the Senior Citizen Affairs (OSCA)',
                'office' => 'OSCA',
                'department_head' => 'Mr. Michael Christian O. Camba',
            ],
        ];

        $usedCodes = [];

        foreach ($departments as $dept) {
            $existing = Department::where('name', $dept['name'])->first();
            if ($existing) {
                $existing->update([
                    'office' => $dept['office'],
                    'department_head' => $dept['department_head'],
                    'is_active' => true,
                ]);
                $usedCodes[] = $existing->code;
                continue;
            }

            $code = $this->generateCode($dept['name'], $usedCodes);
            $usedCodes[] = $code;

            Department::create([
                'name' => $dept['name'],
                'code' => $code,
                'office' => $dept['office'],
                'department_head' => $dept['department_head'],
                'is_active' => true,
            ]);
        }
    }

    private function generateCode(string $name, array $usedCodes): string
    {
        $stopWords = ['OF', 'THE', 'AND', 'CITY', 'OFFICE', 'DEPARTMENT'];
        $clean = preg_replace('/[^A-Za-z0-9\\s]/', ' ', $name) ?? $name;
        $parts = preg_split('/\\s+/', strtoupper(trim($clean)));
        $parts = array_values(array_filter($parts, static function ($part) use ($stopWords) {
            return $part !== '' && ! in_array($part, $stopWords, true);
        }));

        $code = '';
        foreach ($parts as $part) {
            $code .= $part[0];
            if (strlen($code) >= 6) {
                break;
            }
        }

        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/\\s+/', '', $name), 0, 4));
        }

        $code = substr($code, 0, 10);
        $base = $code;
        $suffix = 2;

        while (in_array($code, $usedCodes, true) || Department::where('code', $code)->exists()) {
            $append = (string) $suffix;
            $code = substr($base, 0, max(1, 10 - strlen($append))) . $append;
            $suffix++;
        }

        return $code;
    }
}

