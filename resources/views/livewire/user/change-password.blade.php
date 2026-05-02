<div class="max-w-lg">
    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="changePassword" class="bg-white shadow rounded-lg p-6 space-y-5">
        {{-- Current Password --}}
        <div>
            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                รหัสผ่านปัจจุบัน (Current Password) <span class="text-red-500">*</span>
            </label>
            <input type="password" id="current_password" wire:model="current_password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            @error('current_password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- New Password --}}
        <div>
            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                รหัสผ่านใหม่ (New Password) <span class="text-red-500">*</span>
            </label>
            <input type="password" id="new_password" wire:model="new_password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <p class="mt-1 text-xs text-gray-500">อย่างน้อย 8 ตัวอักษร (minimum 8 characters)</p>
            @error('new_password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirm Password --}}
        <div>
            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                ยืนยันรหัสผ่านใหม่ (Confirm Password) <span class="text-red-500">*</span>
            </label>
            <input type="password" id="confirm_password" wire:model="confirm_password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            @error('confirm_password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Submit --}}
        <div class="pt-2">
            <button type="submit"
                    class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors"
                    wire:loading.attr="disabled">
                <span wire:loading.remove>เปลี่ยนรหัสผ่าน (Change Password)</span>
                <span wire:loading>กำลังดำเนินการ...</span>
            </button>
        </div>
    </form>
</div>
