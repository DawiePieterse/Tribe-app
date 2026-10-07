<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $household_id
 * @property string $name
 * @property string $category
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 */
class Contact extends Model
{
    use HouseholdScoped;

    /** Category keys in display order; labels live in the views (Afrikaans). */
    public const CATEGORIES = ['emergency', 'doctor', 'school', 'babysitter', 'other'];

    /** @var array<string, mixed> */
    protected $attributes = ['category' => 'other'];

    protected $fillable = ['household_id', 'name', 'category', 'phone', 'email', 'notes'];

    /** "082 123 4567" becomes "tel:+27821234567". */
    public function telLink(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '27'.substr($digits, 1);
        }

        return 'tel:+'.$digits;
    }
}
