<form action="{{ route('locale.switch') }}" method="POST" class="locale-switcher">
    @csrf
    <select name="locale" onchange="this.form.submit()" class="fi-dropdown-list-item" style="padding: 0.35rem 0.75rem; border: 1px solid var(--gray-300); border-radius: 0.5rem; background: transparent; font-size: 0.875rem;">
        <option value="ar" @selected(app()->getLocale() === 'ar')>{{ __('general.arabic') }}</option>
        <option value="en" @selected(app()->getLocale() === 'en')>{{ __('general.english') }}</option>
    </select>
</form>
