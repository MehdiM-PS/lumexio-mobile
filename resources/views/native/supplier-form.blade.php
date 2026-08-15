{{--
    `back` (show-navigation-icon) IS required here, despite this screen only
    ever being reached via navigate() (i.e. always pushed). Source-verified
    (no on-device test — no simulator in this environment):

    - NativeRootStackRenderer.swift only draws a manual back chevron when
      `showBack && isRoot` — pushed levels (`isRoot == false`) get the
      chevron for free from SwiftUI's own NavigationStack, no prop needed.
    - BUT this screen is a top-level route with NO NativeLayout, so it
      publishes its own `native_root_stack` sentinel — a DIFFERENT
      NavigationStack from the tab it was pushed from (Suppliers, which
      renders via `native_root_tabs` + PerTabNavigationCoordinator).
    - NativeElementBridge.swift explicitly resets the singleton
      NavigationCoordinator (`isFreshStackMount` → `NavigationCoordinator
      .shared.reset()`) every time the root sentinel TYPE changes to
      `native_root_stack` from something else (tabs, WebView, nothing) —
      not just on the app's first-ever publish. Since this screen is
      always reached FROM the tabs chrome (Suppliers), every navigation here
      is a fresh stack mount: `rootUri` gets cleared, then
      NavigationCoordinator.swift's `receive()` seeds it from the very
      next uri it gets — SupplierForm's own. So this screen is `isRoot:
      true` on ITS stack on every visit, even though PHP's router
      considers it "pushed".
    - `isRoot: true` + `back` unset (defaults false) => NO chevron at all
      and no way back. Hence: explicit `back` here.
--}}
<top-bar title="{{ $supplierId === null ? 'Nouveau fournisseur' : 'Modifier le fournisseur' }}" back />

<column fill class="bg-theme-background gap-3 p-4">
    @if ($lastApiError)
        <row ref="supplier-form-error" class="w-full items-center gap-2 rounded-lg bg-theme-destructive/15 px-4 py-[10]">
            <text class="flex-1 text-sm text-theme-destructive">{{ $lastApiError }}</text>
        </row>
    @endif

    <outlined-text-input ref="supplier-name" native:model="name" label="Nom" />
    <outlined-text-input ref="supplier-contact-name" native:model="contactName" label="Contact" />
    <outlined-text-input ref="supplier-email" native:model="email" label="Email" keyboard="email" />
    <outlined-text-input ref="supplier-phone" native:model="phone" label="Téléphone" keyboard="phone" />
    <outlined-text-input ref="supplier-address" native:model="address" label="Adresse" />
    <outlined-text-input ref="supplier-notes" native:model="notes" label="Notes" />

    <button ref="supplier-submit" variant="primary" :loading="$loading" @press="submit">Enregistrer</button>
</column>
