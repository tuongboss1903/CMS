/**
 * Tailwind config cho Admin + System Admin (Super Admin) - khong dung cho Frontend theme (theme
 * do dung Sass/SCSS thuan, xem resources/frontend/). Mau sac tro thang vao CSS Custom Property
 * da co san trong resources/shared/tokens.css (== public/assets/css/variables.css cu) - KHONG
 * hard-code hex - de co che doi Light/Dark theme (JS ghi [data-theme] len <html>, xem
 * public/assets/js/app.js) tiep tuc hoat dong dung nhu truoc, khong can sua lai JS.
 */
module.exports = {
  content: [
    './themes/default/views/admin/**/*.php',
    './themes/default/views/system_admin/**/*.php',
  ],
  darkMode: ['selector', '[data-theme="dark"]'],
  theme: {
    extend: {
      colors: {
        bg: 'var(--color-bg)',
        'bg-elevated': 'var(--color-bg-elevated)',
        'bg-card': 'var(--color-bg-card)',
        border: 'var(--color-border)',
        'border-subtle': 'var(--color-border-subtle)',
        'text-primary': 'var(--color-text-primary)',
        'text-secondary': 'var(--color-text-secondary)',
        'text-muted': 'var(--color-text-muted)',
        accent: 'var(--color-accent)',
        'accent-hover': 'var(--color-accent-hover)',
        'accent-soft': 'var(--color-accent-soft)',
        'accent-text': 'var(--color-accent-text)',
        danger: 'var(--color-danger)',
        'danger-soft': 'var(--color-danger-soft)',
        warning: 'var(--color-warning)',
        'warning-soft': 'var(--color-warning-soft)',
        info: 'var(--color-info)',
      },
      fontFamily: {
        sans: 'var(--font-sans)',
        mono: 'var(--font-mono)',
      },
      borderRadius: {
        sm: 'var(--radius-sm)',
        md: 'var(--radius-md)',
        lg: 'var(--radius-lg)',
        full: 'var(--radius-full)',
      },
      boxShadow: {
        sm: 'var(--shadow-sm)',
        md: 'var(--shadow-md)',
        lg: 'var(--shadow-lg)',
        glow: 'var(--shadow-glow)',
      },
      spacing: {
        1: 'var(--space-1)',
        2: 'var(--space-2)',
        3: 'var(--space-3)',
        4: 'var(--space-4)',
        5: 'var(--space-5)',
        6: 'var(--space-6)',
        7: 'var(--space-7)',
        8: 'var(--space-8)',
      },
    },
  },
  plugins: [],
};
