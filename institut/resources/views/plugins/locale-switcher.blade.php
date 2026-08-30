<form action="{{ route('locale.switch') }}" method="POST" class="locale-switcher">
    @csrf
    <select
        name="locale"
        onchange="this.form.submit()"
        style="
            padding: 0.3rem 0.65rem;
            border: 1px solid var(--inst-sidebar-border, rgba(0,0,0,0.1));
            border-radius: 0.5rem;
            background: var(--inst-sidebar-bg2, #f1f5f9);
            color: var(--inst-sidebar-text, #1e293b);
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        "
    >
        <option value="ar" @selected(app()->getLocale() === 'ar')>🇾🇪 {{ __('general.arabic') }}</option>
        <option value="en" @selected(app()->getLocale() === 'en')>🇬🇧 {{ __('general.english') }}</option>
    </select>
</form>
