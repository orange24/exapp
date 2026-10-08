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

    {{-- ความคืบหน้าการอ่านชิปพาสปอร์ต
         การอ่านใช้เวลาสองสามวินาที ถ้าหน้าจอเงียบ พนักงานจะคิดว่าแตะไม่ติด
         แล้วยกเล่มออกไปลองใหม่ ซึ่งทำให้การอ่านที่กำลังไปได้ดีล้มจริง ๆ --}}
    @if ($chipStatus !== '')
        <div class="mt-1 flex items-center gap-2
            @if ($chipState === 'reading') text-blue-800 font-semibold
            @elseif ($chipState === 'failed') text-red-700
            @else text-blue-700 @endif">

            @if ($chipState === 'reading')
                <svg class="animate-spin" style="width:14px; height:14px; flex-shrink:0;"
                     viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                </svg>
            @elseif ($chipState === 'failed')
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500" style="flex-shrink:0;"></span>
            @else
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse" style="flex-shrink:0;"></span>
            @endif

            <span>{{ $chipStatus }}</span>
        </div>
    @endif
</div>