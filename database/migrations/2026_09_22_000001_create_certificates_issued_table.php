<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per issued certificate.
 *
 * **The unique index is the idempotency.** One certificate per learner and
 * course is enforced by (subject_type, subject_id, course_id), not by a lock:
 * `lockForUpdate` is a no-op on SQLite, and two CourseCompleted events racing
 * each other must still end in one row. The losing insert fails on the index
 * and the writer reads the winner.
 *
 * **Snapshots, not references.** `learner_name`, `course_title`, the issuer,
 * the signatory and the brand handle are copied at issue time. A certificate
 * states what was true when it was issued; renaming the course, the user or
 * the brand's settings must not change a document someone already showed.
 * Logo, signature image, accent colour and footer stay live (they are looks,
 * not statements).
 *
 * Key lengths stay inside InnoDB's 3072 bytes under utf8mb4:
 * (191 + 64 + 64) × 4 = 1276.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates_issued', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->default(0)->index();
            $table->string('code', 32)->unique();
            $table->string('subject_type', 191);
            $table->string('subject_id', 64);
            $table->string('course_id', 64)->index();
            $table->string('learner_name');
            $table->string('course_title');
            $table->string('issuer_name')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->string('brand_handle', 64)->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'course_id'], 'certificates_issued_subject_course_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates_issued');
    }
};
