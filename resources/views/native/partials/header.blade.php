@php
    $state = \App\Models\LocalState::current();
    // Same config('lumexio.asset_url') + native:image `:fit="1"` pattern
    // Task 5 introduced for Login::logoUrl() — this partial has no bound
    // $this (rendered directly by TabsLayout::navBar(), not @include'd from
    // within a screen component), so the URL is built inline rather than
    // via a screen method.
    $shopLogoUrl = rtrim(config('lumexio.asset_url'), '/').'/images/lumexio-logo.png';
@endphp
{{-- Sole content of the bar's `.principal` slot (see TabsLayout::navBar()).
On iOS, SwiftUI sizes a `.principal` toolbar item by its ideal width, which
for a plain hugging row is just the pill's own natural (short) size — and
then centers that in the bar regardless of `class="w-full"`/alignment
tricks here, since Tailwind has no `min-w` this framework parses into a
`min_width` layout prop. The actual leading-pin fix therefore isn't in
this file: `HasHeaderChrome::headerTitleView()` renders this partial via
`fromViewPartial()` and chains the fluent `->minWidth()` on the resulting
root `<row>` element (`ref="header-shop-row"` below so that call — and
tests — can find it), forcing SwiftUI to allocate a wide-enough `.principal`
region that this row's `items-center` + the trailing `<spacer>` then keep
the pill pinned to the leading edge of, no matter how short the shop name
is. Android's Material3 `TopAppBar` title slot is already leading-aligned
next to the (absent) nav icon, so none of this is needed there. The
account/alerts icon buttons live in the bar's trailing actions instead,
where they get guaranteed space of their own rather than fighting this
pill for room.

`<pressable>` has no dedicated native renderer — it falls back to the
generic container renderer, whose layout direction defaults to COLUMN
unless `node.layout.flexDirection` is explicitly set (SwiftUINodeRenderer's
`containerView`: `node.type == "row" ? .row : (node.layout?.flexDirection
?? .column)`), unlike `<row>`, whose direction is hardcoded to row purely
by its `type`, ignoring layout/class entirely. A bare `flex-row` class on
the pressable itself is therefore a fragile way to get horizontal layout
here — nest a real `<row>` inside instead, matching how every other
multi-child horizontal layout in this app is built. --}}
<row ref="header-shop-row" class="w-full items-center">
    <pressable class="rounded-full bg-theme-surface border border-theme-outline pl-2 pr-3 py-[8]" @press="openShopSwitcher">
        <row class="items-center gap-[6]">
            <native:image ref="header-shop-logo" src="{{ $shopLogoUrl }}" :fit="1" alt="" class="h-[22] w-[22] rounded-[7]" />
            <text class="text-sm font-semibold text-theme-on-surface">{{ $state->active_shop_name ?? 'Choisir une boutique' }}</text>
            <native:icon ref="header-shop-chevron" name="chevron.down" size="10" class="text-theme-on-surface-variant" />
        </row>
    </pressable>
    <spacer />
</row>
