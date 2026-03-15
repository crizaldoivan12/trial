<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedOneDocument(): void
    {
        $dept = Department::create([
            'name' => 'Budget Office',
            'code' => 'BUD',
        ]);

        $encoder = User::factory()->create([
            'role' => 'Encoder',
            'is_active' => true,
            'account_status' => 'Approved',
        ]);

        Document::create([
            'date' => '2026-02-01',
            'encoded_by_id' => $encoder->id,
            'type_of_document' => 'Disbursement Voucher',
            'document_code' => 'CH-2026-BUD-0001',
            'document_number' => 'CH-2026-BUD-0001',
            'pay_claimant' => 'John Doe',
            'particular' => 'Payment for services',
            'amount' => 1234.50,
            'routed_department_id' => $dept->id,
            'status' => 'For Signature',
            'remarks' => 'N/A',
            'date_out' => null,
        ]);
    }

    public function test_excel_export_requires_authentication(): void
    {
        $this->seedOneDocument();

        $res = $this->get('/api/documents/export/excel');
        $res->assertStatus(401);
    }

    public function test_excel_export_requires_admin_role(): void
    {
        $this->seedOneDocument();

        $viewer = User::factory()->create([
            'role' => 'Viewer',
            'is_active' => true,
            'account_status' => 'Approved',
        ]);

        Sanctum::actingAs($viewer);

        $res = $this->get('/api/documents/export/excel');
        $res->assertStatus(403);
    }

    public function test_excel_export_streams_xlsx_with_correct_headers(): void
    {
        $this->seedOneDocument();

        $admin = User::factory()->create([
            'role' => 'Admin',
            'is_active' => true,
            'account_status' => 'Approved',
        ]);

        Sanctum::actingAs($admin);

        $res = $this->get('/api/documents/export/excel');
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $res->assertHeader('content-disposition', 'attachment; filename="report.xlsx"');

        $content = $res->streamedContent();
        $this->assertIsString($content);
        $this->assertStringStartsWith('PK', $content, 'XLSX should be a zip container (PK signature).');
    }

    public function test_pdf_export_requires_admin_role(): void
    {
        $this->seedOneDocument();

        $viewer = User::factory()->create([
            'role' => 'Viewer',
            'is_active' => true,
            'account_status' => 'Approved',
        ]);

        Sanctum::actingAs($viewer);

        $res = $this->get('/api/documents/export/pdf');
        $res->assertStatus(403);
    }

    public function test_pdf_export_streams_pdf_with_correct_headers(): void
    {
        $this->seedOneDocument();

        $admin = User::factory()->create([
            'role' => 'Admin',
            'is_active' => true,
            'account_status' => 'Approved',
        ]);

        Sanctum::actingAs($admin);

        $res = $this->get('/api/documents/export/pdf');
        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $res->assertHeader('content-disposition', 'attachment; filename="report.pdf"');

        $content = $res->streamedContent();
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF', $content, 'PDF should start with %PDF signature.');
    }

    public function test_pdf_export_rejects_too_many_rows(): void
    {
        config(['exports.pdf_max_rows' => 1]);

        $dept = Department::create([
            'name' => 'Budget Office',
            'code' => 'BUD',
        ]);

        $encoder = User::factory()->create([
            'role' => 'Encoder',
            'is_active' => true,
            'account_status' => 'Approved',
        ]);

        Document::create([
            'date' => '2026-02-01',
            'encoded_by_id' => $encoder->id,
            'type_of_document' => 'Type A',
            'document_code' => 'CH-2026-BUD-0001',
            'document_number' => 'CH-2026-BUD-0001',
            'pay_claimant' => 'A',
            'particular' => 'A',
            'amount' => 10,
            'routed_department_id' => $dept->id,
            'status' => 'For Signature',
            'remarks' => null,
            'date_out' => null,
        ]);

        Document::create([
            'date' => '2026-02-02',
            'encoded_by_id' => $encoder->id,
            'type_of_document' => 'Type B',
            'document_code' => 'CH-2026-BUD-0002',
            'document_number' => 'CH-2026-BUD-0002',
            'pay_claimant' => 'B',
            'particular' => 'B',
            'amount' => 20,
            'routed_department_id' => $dept->id,
            'status' => 'For Signature',
            'remarks' => null,
            'date_out' => null,
        ]);

        $admin = User::factory()->create([
            'role' => 'Admin',
            'is_active' => true,
            'account_status' => 'Approved',
        ]);

        Sanctum::actingAs($admin);

        $res = $this->get('/api/documents/export/pdf');
        $res->assertStatus(422);
        $res->assertJson([
            'max_rows' => 1,
        ]);
    }
}
