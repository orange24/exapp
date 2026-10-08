{{-- เสียบบัตรแล้วข้อมูลมาเอง ไม่มีปุ่มให้กด ไฟดวงนี้จึงเป็นสิ่งเดียว
     ที่บอกพนักงานว่า "ไม่มีอะไรเกิดขึ้น" แปลว่าอะไร --}}
<div wire:poll.1500ms="poll" class="mb-3 text-xs">
    @if ($health === 'ready')
        <span class="inline-flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500"></span>
            <span class="text-gray-600">เครื่องอ่านบัตรพร้อม — เสียบบัตรประชาชนได้เลย</span>
        </span>
    @elseif ($health === 'error')
        <span class="inline-flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>
            <span class="text-red-700">เครื่องอ่านบัตรมีปัญหา — กรอกข้อมูลเองไปก่อน</span>
        </span>
    @elseif ($health === 'offline')
        <span class="inline-flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-gray-400"></span>
            <span class="text-gray-500">ไม่ได้ยินจากเครื่องอ่านบัตร — โปรแกรมอาจไม่ได้เปิด</span>
        </span>
    @endif

    {{-- ความคืบหน้าการอ่านชิปพาสปอร์ต — การอ่านใช้เวลาสองสามวินาที
         ถ้าไม่บอกอะไรเลยพนักงานจะยกพาสปอร์ตออกกลางคัน --}}
    @if ($chipStatus !== '')
        <div class="mt-1 flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
            <span class="text-blue-700">{{ $chipStatus }}</span>
        </div>
    @endif
</div>
