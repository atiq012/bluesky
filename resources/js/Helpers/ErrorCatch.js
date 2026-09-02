class ErrorCatch {
    CatchError(eEes) {
        const response = eEes?.response;

        if (!response) {
            console.error(
                "User save request failed before receiving a response:",
                eEes
            );
            Notification.showToast(
                "e",
                error?.message ||
                    "Unable to contact the server. Please try again."
            );
            return;
        }

        if (eEes.response.status == 422) {
            Notification.showToast("e", eEes.response.data.message);
            return;
        } else if (eEes.response.status == 401) {
            Notification.showToast("e", eEes.response.statusText);
            return;
        } else if (eEes.response.status == 500) {
            Notification.showToast("e", eEes.response.data.message);
            return;
        } else if (eEes.response.status == 404) {
            Notification.showToast("e", eEes.response.data.message);
            return;
        }
        Notification.showToast("e", eEes.response.data.data.error);
    }
}
export default ErrorCatch = new ErrorCatch();
