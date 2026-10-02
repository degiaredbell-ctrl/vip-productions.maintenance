// Logo aplikasi memakai aset di public/img/logo.png supaya identitas visual
// tidak lagi memakai logo bawaan Laravel.
export default function ApplicationLogo({ className = 'w-20 h-20', alt = 'PM Preventive', ...props }) {
    return (
        <img
            src="/img/logo.png"
            alt={alt}
            className={`object-contain ${className}`}
            {...props}
        />
    );
}
