<?php

use App\Helpers\PurchaseHelper;
use App\Models\PaymentProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $card_brand = 'visa';
    public string $last4 = '';
    public int|string $exp_month = '';
    public int|string $exp_year = '';
    public ?string $billing_name = '';
    public string $country = 'JP';
    public ?string $postal_code = '';
    public ?string $region = '';
    public ?string $locality = '';
    public ?string $line1 = '';
    public ?string $line2 = '';

    public function mount(): void
    {
        if (!Auth::check()) {
            $this->redirectRoute('login');
            return;
        }

        // 購入完了済みの場合はホームにリダイレクト
        if (PurchaseHelper::isPurchaseCompleted()) {
            $this->redirectRoute('home');
            return;
        }

        $profile = Auth::user()?->paymentProfile;
        if ($profile) {
            $this->card_brand = $profile->card_brand;
            $this->last4 = $profile->last4;
            $this->exp_month = $profile->exp_month;
            $this->exp_year = $profile->exp_year;
            $this->billing_name = (string) ($profile->billing_name ?? '');
            $this->country = $profile->country;
            $this->postal_code = (string) ($profile->postal_code ?? '');
            $this->region = (string) ($profile->region ?? '');
            $this->locality = (string) ($profile->locality ?? '');
            $this->line1 = (string) ($profile->line1 ?? '');
            $this->line2 = (string) ($profile->line2 ?? '');
        }
    }

    public function proceed(): void
    {
        $validated = $this->validate([
            'card_brand' => ['required', 'in:visa,mastercard,amex,jcb,diners,discover,other'],
            'last4' => ['required', 'digits:4'],
            'exp_month' => ['required', 'integer', 'between:1,12'],
            'exp_year' => ['required', 'integer', 'digits:4', 'min:' . date('Y')],
            'billing_name' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
            'locality' => ['nullable', 'string', 'max:100'],
            'line1' => ['nullable', 'string', 'max:150'],
            'line2' => ['nullable', 'string', 'max:150'],
        ]);

        session(['payment_input' => $validated]);

        $this->redirectRoute('payment.confirm');
    }
}; ?>

<section class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-6 text-2xl font-semibold">決済情報（説明用）</h1>

    <form wire:submit="proceed" class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">カードブランド</label>
                <select wire:model="card_brand" class="w-full rounded border px-3 py-2">
                    <option value="visa">VISA</option>
                    <option value="mastercard">Mastercard</option>
                    <option value="amex">AMEX</option>
                    <option value="jcb">JCB</option>
                    <option value="diners">Diners</option>
                    <option value="discover">Discover</option>
                    <option value="other">Other</option>
                </select>
                @error('card_brand')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">カード番号（末尾4桁）</label>
                <input type="text" wire:model="last4" maxlength="4" inputmode="numeric"
                    class="w-full rounded border px-3 py-2" placeholder="4242" />
                @error('last4')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">有効期限（月）</label>
                <input type="number" wire:model="exp_month" min="1" max="12"
                    class="w-full rounded border px-3 py-2" placeholder="12" />
                @error('exp_month')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">有効期限（年）</label>
                <input type="number" wire:model="exp_year" min="{{ date('Y') }}"
                    class="w-full rounded border px-3 py-2" placeholder="{{ date('Y') + 3 }}" />
                @error('exp_year')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium">名義</label>
                <input type="text" wire:model="billing_name" class="w-full rounded border px-3 py-2"
                    placeholder="TARO YAMADA" />
                @error('billing_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">国</label>
                <input type="text" wire:model="country" maxlength="2" class="w-full rounded border px-3 py-2" />
                @error('country')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">郵便番号</label>
                <input type="text" wire:model="postal_code" class="w-full rounded border px-3 py-2" />
                @error('postal_code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">都道府県</label>
                <input type="text" wire:model="region" class="w-full rounded border px-3 py-2" />
                @error('region')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">市区町村</label>
                <input type="text" wire:model="locality" class="w-full rounded border px-3 py-2" />
                @error('locality')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium">住所1</label>
                <input type="text" wire:model="line1" class="w-full rounded border px-3 py-2" />
                @error('line1')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium">住所2</label>
                <input type="text" wire:model="line2" class="w-full rounded border px-3 py-2" />
                @error('line2')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="rounded border px-4 py-2">キャンセル</a>
            <button type="submit" class="rounded bg-black px-4 py-2 text-white">確認へ</button>
        </div>
    </form>
</section>
