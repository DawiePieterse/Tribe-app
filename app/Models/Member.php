<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Everyone in the family. Adults with an email address can log in; children are profiles only.
 * There are no passwords: logins happen with an emailed code or an invite link.
 *
 * @property int $id
 * @property int $household_id
 * @property string $name
 * @property string $kind
 * @property string|null $email
 * @property bool $is_admin
 * @property CarbonImmutable|null $birthday
 * @property string|null $colour
 * @property bool $large_text
 * @property string|null $clothes_size
 * @property string|null $shoe_size
 * @property string|null $favourites
 * @property string|null $allergies
 * @property string|null $wishlist
 * @property CarbonImmutable|null $last_login_at
 * @property-read Household $household
 */
class Member extends Model implements AuthenticatableContract
{
    use Authenticatable;

    public const ADULT = 'adult';

    public const CHILD = 'child';

    protected $fillable = [
        'household_id', 'name', 'kind', 'email', 'is_admin', 'birthday', 'colour', 'large_text',
        'clothes_size', 'shoe_size', 'favourites', 'allergies', 'wishlist',
    ];

    protected $hidden = ['remember_token'];

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => self::ADULT, 'is_admin' => false, 'large_text' => false];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'large_text' => 'boolean',
            'birthday' => 'immutable_date',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    /** @return Attribute<string|null, string|null> */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null || trim($value) === '' ? null : strtolower(trim($value)));
    }

    /** No passwords in Tribe. */
    public function getAuthPassword(): string
    {
        return '';
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return HasMany<Milestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderByDesc('happened_on');
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeChildren(Builder $query): Builder
    {
        return $query->where('kind', self::CHILD);
    }

    public function isChild(): bool
    {
        return $this->kind === self::CHILD;
    }

    public function canLogIn(): bool
    {
        return $this->kind === self::ADULT && $this->email !== null;
    }

    /** Admins and the adults of the child's own household may edit a child's profile. */
    public function canEditProfileOf(Member $child): bool
    {
        return $this->is_admin || $this->household_id === $child->household_id;
    }

    public function displayColour(): string
    {
        return $this->colour ?? $this->household->colour;
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    public function ageOn(CarbonImmutable $date): ?int
    {
        return $this->birthday === null ? null : (int) $this->birthday->diffInYears($date);
    }
}
