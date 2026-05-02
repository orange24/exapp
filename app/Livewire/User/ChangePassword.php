<?php

namespace App\Livewire\User;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $current_password = '';
    public string $new_password = '';
    public string $confirm_password = '';

    protected function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|different:current_password',
            'confirm_password' => 'required|string|same:new_password',
        ];
    }

    protected function messages(): array
    {
        return [
            'current_password.required' => 'กรุณากรอกรหัสผ่านปัจจุบัน',
            'new_password.required'     => 'กรุณากรอกรหัสผ่านใหม่',
            'new_password.min'          => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร',
            'new_password.different'    => 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านปัจจุบัน',
            'confirm_password.required' => 'กรุณายืนยันรหัสผ่านใหม่',
            'confirm_password.same'     => 'รหัสผ่านยืนยันไม่ตรงกับรหัสผ่านใหม่',
        ];
    }

    /**
     * Change the user's password.
     */
    public function changePassword(): void
    {
        $this->validate();

        $user = Auth::user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'รหัสผ่านปัจจุบันไม่ถูกต้อง (Current password is incorrect)');
            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'confirm_password']);

        session()->flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว (Password changed successfully)');
    }

    public function render()
    {
        return view('livewire.user.change-password');
    }
}
