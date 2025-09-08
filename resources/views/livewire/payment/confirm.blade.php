<?php

use App\Models\PaymentProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public array $data = [];

    public function mount(): void
    {
        $this->data = session('payment_input', []);
        if (empty($this->data)) {
            $this->redirectRoute('payment.edit');
        }
    }

    public function store(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirectRoute('login');
            return;
        }

        $validated = validator($this->data, [
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
        ])->validate();

        PaymentProfile::updateOrCreate(['user_id' => $user->id], $validated + ['user_id' => $user->id]);

        session()->forget('payment_input');

        $this->redirectRoute('home');
    }
}; ?>

<section class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-6 text-2xl font-semibold">入力内容の確認</h1>

    <div class="mb-6 space-y-2 text-sm">
        <div>ブランド: <span class="font-medium">{{ strtoupper($data['card_brand'] ?? '') }}</span></div>
        <div>末尾4桁: <span class="font-medium">{{ $data['last4'] ?? '' }}</span></div>
        <div>有効期限: <span class="font-medium">{{ $data['exp_month'] ?? '' }}/{{ $data['exp_year'] ?? '' }}</span></div>
        <div>名義: <span class="font-medium">{{ $data['billing_name'] ?? '' }}</span></div>
        <div>住所: <span class="font-medium">{{ $data['postal_code'] ?? '' }} {{ $data['region'] ?? '' }}
                {{ $data['locality'] ?? '' }} {{ $data['line1'] ?? '' }} {{ $data['line2'] ?? '' }}</span></div>
        <div>国: <span class="font-medium">{{ $data['country'] ?? '' }}</span></div>
    </div>

    <div class="flex items-center gap-3">
        <a href="{{ route('payment.edit') }}" class="rounded border px-4 py-2">戻る</a>
        <button wire:click="store" class="rounded bg-black px-4 py-2 text-white">登録</button>
    </div>
</section>
